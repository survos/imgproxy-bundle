<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Service;

use InvalidArgumentException;
use Survos\ImgproxyBundle\SurvosImgproxyBundle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Attribute\AsTwigFilter;

final class ImgproxyUrlBuilder
{
    /**
     * Friendly info-option names mapped to imgproxy's short option names.
     * Anything not listed here is passed through verbatim.
     *
     * @see https://docs.imgproxy.net/usage/getting_info
     */
    private const INFO_ALIASES = [
        'blurhash'        => 'bh',
        'thumb_hash'      => 'th',
        'average'         => 'avg',
        'dominant_colors' => 'dc',
        'detect_objects'  => 'do',
        // imgproxy's option is `classify` (short `cl`); accept the longer alias too.
        'classify_objects' => 'classify',
        'video_meta'      => 'vm',
        'colorspace'      => 'cs',
        'pages_number'    => 'pn',
        'sample_format'   => 'sf',
        'perceptual_hash' => 'phash',
        'calc_hashsums'   => 'chs',
    ];

    public function __construct(
        public readonly ?string $host = null,
        private readonly ?string $key = null,
        private readonly ?string $salt = null,
        private readonly array $presets = SurvosImgproxyBundle::DEFAULT_PRESETS,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
    }

    public function resize(
        string $url,
        int    $width,
        int    $height,
        string $resizeType = 'fit',
        string $format = 'jpg',
    ): string {
        $this->assertHost();

        $options = sprintf('rs:%s:%d:%d:0', $resizeType, $width, $height);
        $path = sprintf('/%s/plain/%s@%s', $options, $this->encodePlain($url), $format);

        return rtrim($this->host, '/') . '/' . $this->sign($path) . $path;
    }

    /**
     * Build a signed URL from a named preset, expanding its size/quality/format
     * inline:
     *
     *   {host}/{signature}/rs:fit:400:400:0:0/q:80/f:webp/plain/{source}
     *
     * Expanding client-side (rather than referencing a server-side `preset:NAME`)
     * keeps the bundle self-contained — no imgproxy server preset config is
     * required. Because every caller of a given preset emits a byte-identical
     * processing string, the imgproxy/S3 cache stays hot. Pass $format to
     * override the preset's output format.
     */
    #[AsTwigFilter('imgproxy')]
    public function resizePreset(string $url, string $preset = 'thumb', ?string $format = null): string
    {
        if (!isset($this->presets[$preset])) {
            throw new InvalidArgumentException(sprintf('Unknown imgproxy preset "%s". Available: %s', $preset, implode(', ', array_keys($this->presets))));
        }

        $this->assertHost();

        $p = $this->presets[$preset];

        $options = sprintf('rs:%s:%d:%d:0:0', $p['resize'] ?? 'fit', $p['width'], $p['height']);
        if (!empty($p['quality'])) {
            $options .= sprintf('/q:%d', $p['quality']);
        }
        // strip_metadata: null → imgproxy default; false → sm:0 (keep exif/iptc/xmp); true → sm:1
        if (array_key_exists('strip_metadata', $p) && $p['strip_metadata'] !== null) {
            $options .= sprintf('/sm:%d', $p['strip_metadata'] ? 1 : 0);
        }
        $options .= sprintf('/f:%s', $format ?? $p['format'] ?? 'jpg');

        $path = sprintf('/%s/plain/%s', $options, $this->encodePlain($url));

        return rtrim($this->host, '/') . '/' . $this->sign($path) . $path;
    }

    public function thumbnail(string $url, int $size = 512): string
    {
        return $this->resize($url, $size, $size);
    }

    public function aiThumbnail(string $url): string
    {
        return $this->resizePreset($url, 'observe');
    }

    public function aiHires(string $url): string
    {
        return $this->resizePreset($url, 'archive');
    }

    /**
     * Returns the imgProxy IIIF Image API v3 base URL for any source image.
     *
     * Append standard IIIF path segments to get any size:
     *   {iiifBase}/full/!512,512/0/default.webp   → thumbnail
     *   {iiifBase}/full/max/0/default.jpg          → original
     *
     * This lets non-IIIF sources (e.g. Fortepan) participate in the same
     * consistent URL scheme as native IIIF collections.
     */
    public function iiifBase(string $sourceUrl): string
    {
        $this->assertHost();

        $path = '/iiif3/' . $this->encodeBase64Source($sourceUrl);

        return rtrim($this->host, '/') . '/' . $this->sign($path) . $path;
    }

    /**
     * Build a signed URL with an arbitrary imgproxy processing string, e.g. "rs:fit:1200:0/f:webp".
     * Use this when the preset/resize helpers don't cover your needs.
     */
    public function buildUrl(string $url, string $processing): string
    {
        $this->assertHost();

        $path = sprintf('/%s/plain/%s', trim($processing, '/'), $this->encodePlain($url));

        return rtrim($this->host, '/') . '/' . $this->sign($path) . $path;
    }

    /**
     * Build a signed URL for imgproxy's PRO /info endpoint.
     *
     * The signature, salt, and HMAC construction are identical to processing
     * URLs — only the URL shape differs: the `/info` prefix sits *outside* the
     * signed portion, which is `/{options}/{base64_source}`.
     *
     *   {host}/info/{signature}/{options}/{base64url_source}
     *
     * NOTE: the /info endpoint requires the **base64url-encoded** source URL.
     * Unlike the processing endpoint, it rejects the `plain/` form with a
     * "404 Invalid URL" — verified against imgproxy PRO v4.0.3.
     *
     * @param array<int|string, mixed>|string $options info options — see buildInfoOptions()
     * @see https://docs.imgproxy.net/usage/getting_info
     */
    public function infoUrl(string $url, array|string $options = []): string
    {
        $this->assertHost();

        $opts = $this->buildInfoOptions($options);
        $path = ($opts === '' ? '' : '/' . $opts) . '/' . $this->encodeBase64Source($url);

        return rtrim($this->host, '/') . '/info/' . $this->sign($path) . $path;
    }

    /**
     * Fetch and decode image metadata from the PRO /info endpoint.
     *
     * @param array<int|string, mixed>|string $options info options — see buildInfoOptions()
     * @return array<string, mixed> decoded JSON (e.g. width, height, format, size, objects, …)
     */
    public function info(string $url, array|string $options = []): array
    {
        if (!$this->httpClient instanceof HttpClientInterface) {
            throw new \RuntimeException('imgproxy info() requires an HTTP client. Ensure symfony/http-client is installed and the service is autowired.');
        }

        return $this->httpClient->request('GET', $this->infoUrl($url, $options))->toArray();
    }

    /**
     * Run imgproxy's PRO image classification on a source image.
     *
     * @param int           $topK    number of classes to return (top-K by confidence)
     * @param array<string> $classes optional whitelist of classes; empty = all trained classes
     * @return array<string, mixed> decoded /info response including the classification list
     * @see https://docs.imgproxy.net/usage/getting_info
     */
    public function classify(string $url, int $topK = 5, array $classes = []): array
    {
        $token = 'classify:' . implode(':', [$topK, ...$classes]);

        return $this->info($url, [$token]);
    }

    /**
     * Run imgproxy's PRO object detection on a source image.
     *
     * @return array<string, mixed> decoded /info response including detected objects + coordinates
     */
    public function detectObjects(string $url): array
    {
        return $this->info($url, ['detect_objects:1']);
    }

    public function hasPreset(string $preset): bool
    {
        return isset($this->presets[$preset]);
    }

    public function getPresets(): array
    {
        return $this->presets;
    }

    private function assertHost(): void
    {
        if (!$this->host || $this->host === '') {
            throw new \RuntimeException('imgproxy host is not configured. Set the IMGPROXY_HOST environment variable.');
        }
    }

    /**
     * Escape a source URL for imgproxy's `plain/` segment. Non-http schemes
     * (s3://, gs://, abs://, swift://) pass through unchanged — the `://` and
     * key separators are left intact.
     */
    private function encodePlain(string $url): string
    {
        return strtr($url, ['&' => '%26', '=' => '%3D', '?' => '%3F', '@' => '%40']);
    }

    /**
     * URL-safe base64 of a source URL, used by the /info endpoint and as the
     * IIIF identifier. Padding stripped, `+/` mapped to `-_` per imgproxy.
     */
    private function encodeBase64Source(string $url): string
    {
        return rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    }

    /**
     * Normalize info options into imgproxy's slash-joined option path.
     *
     * Accepts:
     *  - a raw string, returned trimmed (e.g. "dimensions:1/bh:4:3")
     *  - a list of raw tokens, passed through verbatim
     *      ['dimensions', 'blurhash:4:3', 'classify_objects:5:cat:dog']
     *  - key => value pairs, where bool true→":1", false→":0", scalars→":value"
     *      ['palette' => 8, 'size' => true, 'detect_objects' => false]
     *
     * Friendly names (blurhash, average, detect_objects, …) are mapped to their
     * imgproxy short names; unknown names pass through verbatim.
     *
     * @param array<int|string, mixed>|string $options
     */
    private function buildInfoOptions(array|string $options): string
    {
        if (is_string($options)) {
            return trim($options, '/');
        }

        $tokens = [];
        foreach ($options as $key => $value) {
            if (is_int($key)) {
                // raw token, e.g. "dimensions", "dimensions:1" or "classify:5:cat:dog".
                // imgproxy requires the name:value form — a bare "dimensions" is NOT
                // recognized as an option and gets folded into the base64 source URL,
                // so valueless tokens get an implicit ":1".
                $token = (string) $value;
                [$name, $rest] = array_pad(explode(':', $token, 2), 2, null);
                $tokens[] = (self::INFO_ALIASES[$name] ?? $name) . ':' . ($rest ?? '1');
                continue;
            }

            $name = self::INFO_ALIASES[$key] ?? $key;
            if (is_bool($value)) {
                $tokens[] = $name . ':' . ($value ? '1' : '0');
            } elseif (is_array($value)) {
                $tokens[] = $name . ':' . implode(':', $value);
            } else {
                $tokens[] = $name . ':' . $value;
            }
        }

        return implode('/', array_filter($tokens, static fn (string $t): bool => $t !== ''));
    }

    private function sign(string $path): string
    {
        if (!$this->key || !$this->salt) {
            return 'insecure';
        }

        $digest = hash_hmac(
            'sha256',
            pack('H*', $this->salt) . $path,
            pack('H*', $this->key),
            true,
        );

        return rtrim(strtr(base64_encode($digest), '+/', '-_'), '=');
    }
}
