<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Dto;

use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * Embedded metadata blocks returned by imgproxy /info.
 *
 * EXIF, IPTC, and XMP have overlapping concepts but inconsistent tag names.
 * Keep the original blocks intact and expose normalized accessors for common
 * cultural-heritage fields.
 */
final readonly class ImgproxyEmbeddedMetadata
{
    /**
     * @param array<string, mixed> $exif
     * @param array<string, mixed> $iptc
     * @param array<string, mixed> $xmp
     */
    public function __construct(
        public array $exif = [],
        public array $iptc = [],
        public array $xmp = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromInfoArray(array $data): self
    {
        return new self(
            exif: self::arrayOrEmpty($data['exif'] ?? null),
            iptc: self::arrayOrEmpty($data['iptc'] ?? null),
            xmp: self::arrayOrEmpty($data['xmp'] ?? null),
        );
    }

    #[Ignore]
    public function isEmpty(): bool
    {
        return $this->exif === [] && $this->iptc === [] && $this->xmp === [];
    }

    public function copyright(): ?string
    {
        return $this->firstString([
            $this->xmp['dc:rights'] ?? null,
            $this->xmp['rights'] ?? null,
            $this->xmp['xmpRights:UsageTerms'] ?? null,
            $this->iptc['CopyrightNotice'] ?? null,
            $this->iptc['Copyright'] ?? null,
            $this->exif['Copyright'] ?? null,
        ]);
    }

    public function credit(): ?string
    {
        return $this->firstString([
            $this->xmp['photoshop:Credit'] ?? null,
            $this->xmp['credit'] ?? null,
            $this->iptc['Credit'] ?? null,
            $this->iptc['Byline'] ?? null,
            $this->exif['Artist'] ?? null,
        ]);
    }

    public function creator(): ?string
    {
        return $this->firstString([
            $this->xmp['dc:creator'] ?? null,
            $this->xmp['creator'] ?? null,
            $this->iptc['Byline'] ?? null,
            $this->exif['Artist'] ?? null,
        ]);
    }

    public function rightsUri(): ?string
    {
        return $this->firstUri([
            $this->xmp['xmpRights:WebStatement'] ?? null,
            $this->xmp['web_statement'] ?? null,
            $this->xmp['rights_uri'] ?? null,
            $this->iptc['CopyrightInfoURL'] ?? null,
        ]);
    }

    /** @return array<string, mixed> */
    public function normalized(): array
    {
        return array_filter([
            'copyright' => $this->copyright(),
            'credit' => $this->credit(),
            'creator' => $this->creator(),
            'rights_uri' => $this->rightsUri(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, mixed> */
    public function toArray(bool $includeRaw = false): array
    {
        $data = $this->normalized();

        if ($includeRaw) {
            $data['exif'] = $this->exif;
            $data['iptc'] = $this->iptc;
            $data['xmp'] = $this->xmp;
        }

        return array_filter($data, static fn (mixed $value): bool => $value !== [] && $value !== null);
    }

    /** @return array{exif: array<string, mixed>, iptc: array<string, mixed>, xmp: array<string, mixed>} */
    #[Ignore]
    public function raw(): array
    {
        return [
            'exif' => $this->exif,
            'iptc' => $this->iptc,
            'xmp' => $this->xmp,
        ];
    }

    /** @param list<mixed> $values */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $value = reset($value);
            }

            if (!is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param list<mixed> $values */
    private function firstUri(array $values): ?string
    {
        foreach ($values as $value) {
            $value = $this->firstString([$value]);
            if ($value !== null && filter_var($value, FILTER_VALIDATE_URL) !== false) {
                return $value;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private static function arrayOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
