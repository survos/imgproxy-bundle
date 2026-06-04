<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Dto;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Typed view of imgproxy's /info response.
 *
 * The raw payload is kept because enabled info options vary by imgproxy
 * version/build, but common image metadata is promoted to stable properties.
 */
final readonly class ImgproxyInfo
{
    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $exif
     * @param array<string, mixed> $iptc
     * @param array<string, mixed> $xmp
     * @param array<string, mixed> $dominantColors
     * @param list<mixed>          $palette
     * @param list<mixed>          $classification
     * @param list<mixed>          $objects
     */
    public function __construct(
        #[Ignore]
        public array $raw,
        public ?int $width = null,
        public ?int $height = null,
        public ?int $orientation = null,
        public ?string $format = null,
        #[SerializedName('mime_type')]
        public ?string $mimeType = null,
        public ?int $size = null,
        public ?string $blurhash = null,
        public ?array $average = null,
        #[SerializedName('dominant_colors')]
        public array $dominantColors = [],
        public array $palette = [],
        public array $classification = [],
        public array $objects = [],
        #[SerializedName('embedded_metadata')]
        public ImgproxyEmbeddedMetadata $embeddedMetadata = new ImgproxyEmbeddedMetadata(),
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $dimensions = self::arrayOrEmpty($data['dimensions'] ?? null);

        return new self(
            raw: $data,
            width: self::intOrNull($data['width'] ?? $dimensions['width'] ?? null),
            height: self::intOrNull($data['height'] ?? $dimensions['height'] ?? null),
            orientation: self::intOrNull($data['orientation'] ?? $dimensions['orientation'] ?? null),
            format: self::stringOrNull($data['format'] ?? null),
            mimeType: self::stringOrNull($data['mime_type'] ?? $data['mimeType'] ?? null),
            size: self::intOrNull($data['size'] ?? null),
            blurhash: self::stringOrNull($data['blurhash'] ?? $data['bh'] ?? null),
            average: self::arrayOrNull($data['average'] ?? $data['avg'] ?? null),
            dominantColors: self::arrayOrEmpty($data['dominant_colors'] ?? $data['dc'] ?? null),
            palette: self::listOrEmpty($data['palette'] ?? null),
            classification: self::listOrEmpty($data['classification'] ?? $data['classify'] ?? null),
            objects: self::listOrEmpty($data['objects'] ?? $data['detected_objects'] ?? $data['do'] ?? null),
            embeddedMetadata: ImgproxyEmbeddedMetadata::fromInfoArray($data),
        );
    }

    public function aspectRatio(): ?float
    {
        if (!$this->width || !$this->height) {
            return null;
        }

        return $this->width / $this->height;
    }

    public function iiifFormat(): ?string
    {
        if ($this->mimeType !== null && $this->mimeType !== '') {
            return $this->mimeType;
        }

        return match (strtolower((string) $this->format)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'tif', 'tiff' => 'image/tiff',
            'jp2' => 'image/jp2',
            'avif' => 'image/avif',
            default => null,
        };
    }

    /** @return array{width: int, height: int}|null */
    public function sizePair(): ?array
    {
        if (!$this->width || !$this->height) {
            return null;
        }

        return [
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        return array_filter([
            'width' => $this->width,
            'height' => $this->height,
            'orientation' => $this->orientation,
            'format' => $this->format,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'blurhash' => $this->blurhash,
            'average' => $this->average,
            'dominant_colors' => $this->dominantColors ?: null,
            'palette' => $this->palette ?: null,
            'classification' => $this->classification ?: null,
            'objects' => $this->objects ?: null,
            'embedded_metadata' => $this->embeddedMetadata->isEmpty() ? null : $this->embeddedMetadata->toArray(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    #[SerializedName('aspect_ratio')]
    public function getAspectRatio(): ?float
    {
        return $this->aspectRatio();
    }

    #[SerializedName('iiif_format')]
    public function getIiifFormat(): ?string
    {
        return $this->iiifFormat();
    }

    #[SerializedName('size_pair')]
    public function getSizePair(): ?array
    {
        return $this->sizePair();
    }

    /** @return array<string, mixed> */
    public function toArray(bool $includeRaw = false): array
    {
        $data = $this->normalized();

        if ($includeRaw) {
            $data['raw'] = $this->raw;
        }

        return $data;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /** @return array<string, mixed> */
    private static function arrayOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @return array<string, mixed>|null */
    private static function arrayOrNull(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    /** @return list<mixed> */
    private static function listOrEmpty(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_is_list($value) ? $value : array_values($value);
    }
}
