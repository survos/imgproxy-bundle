# survos/imgproxy-bundle

Symfony bundle for generating signed [imgproxy](https://imgproxy.net) URLs. Provides preset-based resizing with no external PHP library dependency — signing is implemented natively.

## Installation

```bash
composer require survos/imgproxy-bundle
```

Set your imgproxy credentials in `.env`:

```dotenv
IMGPROXY_HOST=https://imgproxy.example.com
IMGPROXY_KEY=your-hex-key
IMGPROXY_SALT=your-hex-salt
```

## Configuration

```yaml
# config/packages/survos_imgproxy.yaml
survos_imgproxy:
    host: '%env(IMGPROXY_HOST)%'
    key: '%env(IMGPROXY_KEY)%'
    salt: '%env(IMGPROXY_SALT)%'
    presets:
        ai:     { width: 512,  height: 512,  resize: fit }
        thumb:  { width: 300,  height: 300,  resize: fit }
        small:  { width: 192,  height: 192,  resize: fit }
        medium: { width: 600,  height: 400,  resize: fit }
        large:  { width: 1200, height: 800,  resize: fit }
```

Presets are optional — the defaults above are used if `presets` is omitted.

If `host` is not configured the builder returns the original URL unchanged, so the bundle is safe to install before imgproxy is running.

## Usage

```php
use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;

class MyService
{
    public function __construct(private ImgproxyUrlBuilder $imgproxy) {}

    public function example(string $sourceUrl): void
    {
        // Preset-based (recommended)
        $url = $this->imgproxy->resizePreset($sourceUrl, 'ai');     // 512×512 for AI vision
        $url = $this->imgproxy->resizePreset($sourceUrl, 'thumb');  // 300×300

        // Convenience shorthand
        $url = $this->imgproxy->aiThumbnail($sourceUrl);            // equivalent to resizePreset('ai')
        $url = $this->imgproxy->thumbnail($sourceUrl, 256);         // arbitrary square size

        // Explicit dimensions
        $url = $this->imgproxy->resize($sourceUrl, 800, 600, 'fill');
    }
}
```

## Twig filter

The `imgproxy` filter is registered automatically — no extra config needed.

```twig
{# preset (default: thumb) #}
{{ image.sourceUrl | imgproxy }}
{{ image.sourceUrl | imgproxy('ai') }}
{{ image.sourceUrl | imgproxy('large', 'webp') }}
```

## Image info & metadata (v4 PRO)

imgproxy v4 PRO exposes an `/info` endpoint that returns image metadata —
dimensions, format, file size, blurhash, average/dominant color, palette,
EXIF/IPTC/XMP, **object detection**, and **image classification** — without
imgproxy (or you) downloading and decoding the whole image yourself.

```php
// Fetch metadata (signed URL is built + fetched + JSON-decoded for you)
$meta = $this->imgproxy->info($sourceUrl, ['size', 'format', 'dimensions']);
// => ['size' => 123456, 'format' => 'jpeg', 'mime_type' => 'image/jpeg',
//     'width' => 7360, 'height' => 4912, 'orientation' => 1]

// Image classification (PRO ML) — top-K classes with confidence scores.
$result = $this->imgproxy->classify($sourceUrl, topK: 5);
// => ['classification' => [['class_id' => 90, 'name' => 'Cat', 'confidence' => 0.97]], ...]
// optionally restrict to a known class list:
$result = $this->imgproxy->classify($sourceUrl, 5, ['cat', 'dog', 'bird']);

// Object detection (PRO ML) — bounding boxes (relative coords) + confidence.
$objects = $this->imgproxy->detectObjects($sourceUrl);

// Just build the signed /info URL (no fetch):
$url = $this->imgproxy->infoUrl($sourceUrl, ['dimensions', 'blurhash:4:3']);
```

`info()` / `classify()` / `detectObjects()` require an HTTP client (the bundle
autowires `symfony/http-client`). `infoUrl()` only builds the signed URL and has
no HTTP dependency. Note `classify`/`detect_objects` need the imgproxy PRO **ML**
build (e.g. `docker.imgproxy.pro/imgproxy:vX-ml`).

**Option formats** accepted by `info()` / `infoUrl()`:

```php
// list of tokens — a valueless token gets an implicit ":1"
['dimensions', 'blurhash:4:3', 'classify:5:cat:dog']
//  ^ becomes dimensions:1

// key => value pairs (bool → :1/:0, scalar → :value)
['palette' => 8, 'detect_objects' => true, 'size' => false]

// a raw option path string (passed through as-is)
'dimensions:1/bh:4:3'
```

> **Two non-obvious imgproxy requirements** the builder handles for you:
> the `/info` endpoint takes the **base64url-encoded** source (the `plain/` form
> returns `404 Invalid URL`), and every option **must** use the `name:value`
> form — a bare `dimensions` is silently folded into the source URL. The builder
> base64-encodes the source and appends `:1` to valueless tokens automatically.

Friendly names are mapped to imgproxy's short names automatically
(`blurhash`→`bh`, `average`→`avg`, `dominant_colors`→`dc`, `detect_objects`→`do`,
`classify_objects`→`classify`, `video_meta`→`vm`, …). Full option reference:
<https://docs.imgproxy.net/usage/getting_info>.

> The server must allow these options via `IMGPROXY_ALLOWED_INFO_OPTIONS` (or
> leave it blank to allow all).

### Command

```bash
bin/console imgproxy:info https://images.example.org/photo.jpg \
    --opt=dimensions --opt=format --opt=classify:5
bin/console imgproxy:info s3://bucket/photo.jpg --opt=blurhash:4:3 --json
```

With no `--opt`, defaults to `size`, `format`, `dimensions`.

### HTTP endpoint

```
GET /imgproxy/info?url=<source>&opts[]=dimensions&opts[]=classify_objects:5
```

Returns the decoded metadata JSON. Use this server-side route (not the public
Stimulus path) for signed/private sources, since signing needs the secret key.

## Stimulus controller

For public source URLs, the bundle includes a Stimulus controller that can
rewrite rendered image tags in the browser. This is useful for Meilisearch and
DataTables renderers where storing or precomputing thumbnail URLs would add
noise.

```twig
{% set _sc = '@survos/imgproxy/imgproxy' %}
{% set host = imgproxy_host %}

<div {{ stimulus_controller(_sc, {
    host: host,
    preset: 'thumb'
}) }}>
    <img
        {{ stimulus_target(_sc, 'image') }}
        data-imgproxy-url="https://images.example.org/full-size.jpg"
        data-imgproxy-preset="ai"
        alt=""
    >
</div>
```

When an `image` target is connected, the controller sets `src` to an unsigned
imgproxy URL such as `/insecure/rs:fit:512:512:0/plain/...@jpg` and marks the
element with `data-imgproxy-done="1"`. You may also put the full public URL in
`src`, `data-src`, or `data-url`; `data-imgproxy-url` is preferred because it
avoids the browser starting a full-size image request before Stimulus connects.

Per-image attributes:

| Attribute | Purpose |
|-----------|---------|
| `data-imgproxy-url` | Full public source URL to proxy |
| `data-imgproxy-preset` | Preset name, default `thumb` |
| `data-imgproxy-format` | Output format, default `jpg` |
| `data-imgproxy-host` | Override the wrapper host value |

Signed/private URLs should use a server endpoint instead of this public
controller path, because signing requires the secret key and salt.

## AiThumbnailProviderInterface

Implement this interface on entities that can provide their own low-resolution URL for AI vision tasks. When present, the AI workflow uses `getAiSmallUrl()` instead of the full-resolution source — avoiding unnecessary costs on large images.

```php
use Survos\ImgproxyBundle\Contract\AiThumbnailProviderInterface;

class MyMedia implements AiThumbnailProviderInterface
{
    public function getAiSmallUrl(): string
    {
        // return a pre-computed URL, or generate via imgproxy, liip, etc.
        return $this->smallUrl ?? $this->sourceUrl;
    }
}
```

If the entity does not implement this interface, the workflow falls back to the full-resolution URL.

## Presets

| Preset    | Width | Height | Quality | Format | Use case                          |
|-----------|-------|--------|---------|--------|-----------------------------------|
| `tiny`    | 200   | 200    | 70      | webp   | Dense browsing grids, list views  |
| `thumb`   | 400   | 400    | 80      | webp   | UI thumbnails / search hits       |
| `observe` | 512   | 512    | 80      | webp   | AI vision (GPT-4o, Claude, etc)   |
| `display` | 600   | 400    | 80      | webp   | Content cards, detail views       |
| `archive` | 3000  | 3000   | 88      | webp   | Full-size / lightbox / archival   |

Each preset is expanded inline into the imgproxy processing string
(`rs:fit:W:H:0:0/q:Q/f:webp`), so no server-side imgproxy preset config is
required. Because every caller of a given preset emits a byte-identical URL,
the imgproxy/S3 cache stays hot.

> These names mirror the presets defined on the imgproxy server. We may later
> switch the builder to reference them by name (`preset:NAME`) once the
> server-side set is finalized — keep the two in sync until then.

## Unsecured mode

If no key/salt is set, URLs are generated with the `insecure` token. This is fine for a local imgproxy instance running without signature verification, but **never use it in production**.

## S3 / object-storage sources

imgproxy can fetch source images straight from object storage. Pass an
`s3://bucket/key` (or `gs://`, `abs://`, `swift://`) URL anywhere this bundle
accepts a source — the scheme passes through `plain/` untouched:

```php
$this->imgproxy->resizePreset('s3://my-bucket/photos/cat.jpg', 'thumb');
// → {host}/{sig}/rs:fit:300:300:0/plain/s3://my-bucket/photos/cat.jpg@jpg

$this->imgproxy->info('s3://my-bucket/photos/cat.jpg', ['dimensions']);
```

Enable it on the **imgproxy server** with `IMGPROXY_USE_S3=true` (plus
`IMGPROXY_S3_REGION` / credentials). See the imgproxy docs for GCS/ABS/Swift.

> The encrypted-source (`enc/`) variant is not yet supported by this bundle
> (it needs a shared AES key). TODO.

## Persistent cache & S3 output (v4 PRO)

v4 PRO can persist processed images in object storage as an **internal cache**,
so you no longer need a caching reverse proxy in front of imgproxy. These are
configured on the **imgproxy server itself** (env vars read by the imgproxy
container — *not* by Symfony); this bundle scaffolds them in the recipe's
`env.txt` and documents them here.

```yaml
# docker-compose.yaml (excerpt)
services:
  imgproxy:
    image: darthsim/imgproxy:latest   # PRO image for cache/info/ML features
    environment:
      IMGPROXY_KEY: "${IMGPROXY_KEY}"
      IMGPROXY_SALT: "${IMGPROXY_SALT}"
      # --- persistent internal cache → S3 ---
      IMGPROXY_CACHE_USE: s3
      IMGPROXY_CACHE_BUCKET: my-imgproxy-cache
      IMGPROXY_CACHE_PATH_PREFIX: cache
      IMGPROXY_CACHE_S3_REGION: us-east-1
      # IMGPROXY_CACHE_S3_ENDPOINT: https://…   # R2 / Spaces / MinIO
      # --- fetch sources from S3 ---
      IMGPROXY_USE_S3: "true"
      IMGPROXY_S3_REGION: us-east-1
      # --- info endpoint ---
      IMGPROXY_ALLOWED_INFO_OPTIONS: size,format,dimensions,classify_objects,detect_objects,blurhash
      AWS_ACCESS_KEY_ID: "${AWS_ACCESS_KEY_ID}"
      AWS_SECRET_ACCESS_KEY: "${AWS_SECRET_ACCESS_KEY}"
```

Key facts about the v4 cache: the URL signature is **not** part of the cache key
(so you can rotate keys without invalidating the cache), `/info` responses are
**not** cached, and there is no active invalidation beyond the `cachebuster`
processing option / `IMGPROXY_ETAG_BUSTER`. Adapters: `fs`, `s3`, `gcs`, `abs`,
`swift`. See <https://imgproxy.net/blog/v4-caching/>.

## Related

- `survos/media-bundle` — uses this bundle for all imgproxy URL generation
- `survos/ai-workflow-bundle` — checks `AiThumbnailProviderInterface` to select the image URL for low-res AI passes
