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

| Preset   | Width | Height | Use case                        |
|----------|-------|--------|---------------------------------|
| `ai`     | 512   | 512    | AI vision (GPT-4o, Claude, etc) |
| `thumb`  | 300   | 300    | UI thumbnails                   |
| `small`  | 192   | 192    | ThumbHash source, list views    |
| `medium` | 600   | 400    | Content cards                   |
| `large`  | 1200  | 800    | Hero images, lightbox           |

## Unsecured mode

If no key/salt is set, URLs are generated with the `insecure` token. This is fine for a local imgproxy instance running without signature verification, but **never use it in production**.

## Related

- `survos/media-bundle` — uses this bundle for all imgproxy URL generation
- `survos/ai-workflow-bundle` — checks `AiThumbnailProviderInterface` to select the image URL for low-res AI passes
