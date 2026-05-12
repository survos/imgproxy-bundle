<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Service;

use InvalidArgumentException;
use Survos\ImgproxyBundle\SurvosImgproxyBundle;

final class ImgproxyUrlBuilder
{
    public function __construct(
        private readonly ?string $host = null,
        private readonly ?string $key = null,
        private readonly ?string $salt = null,
        private readonly array $presets = SurvosImgproxyBundle::DEFAULT_PRESETS,
    ) {
    }

    public function resize(
        string $url,
        int    $width,
        int    $height,
        string $resizeType = 'fit',
        string $format = 'jpg',
    ): string {
        if (!$this->host) {
            return $url;
        }

        $encodedUrl = strtr($url, ['&' => '%26', '=' => '%3D', '?' => '%3F', '@' => '%40']);
        $options = sprintf('rs:%s:%d:%d:0', $resizeType, $width, $height);
        $path = sprintf('/%s/plain/%s@%s', $options, $encodedUrl, $format);

        return rtrim($this->host, '/') . '/' . $this->sign($path) . $path;
    }

    public function resizePreset(string $url, string $preset = 'thumb', string $format = 'jpg'): string
    {
        if (!isset($this->presets[$preset])) {
            throw new InvalidArgumentException(sprintf('Unknown imgproxy preset "%s". Available: %s', $preset, implode(', ', array_keys($this->presets))));
        }

        $p = $this->presets[$preset];

        return $this->resize($url, $p['width'], $p['height'], $p['resize'], $format);
    }

    public function thumbnail(string $url, int $size = 512): string
    {
        return $this->resize($url, $size, $size);
    }

    public function aiThumbnail(string $url): string
    {
        return $this->resizePreset($url, 'ai');
    }

    public function hasPreset(string $preset): bool
    {
        return isset($this->presets[$preset]);
    }

    public function getPresets(): array
    {
        return $this->presets;
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
