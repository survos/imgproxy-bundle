<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Survos\ImgproxyBundle\Dto\ImgproxyEmbeddedMetadata;
use Survos\ImgproxyBundle\Dto\ImgproxyInfo;

final class ImgproxyInfoTest extends TestCase
{
    public function testFromArrayPromotesCommonInfoFields(): void
    {
        $info = ImgproxyInfo::fromArray([
            'dimensions' => [
                'width' => '3000',
                'height' => 2000,
                'orientation' => '1',
            ],
            'format' => 'jpeg',
            'mime_type' => 'image/jpeg',
            'size' => '123456',
            'bh' => 'LEHV6nWB2yk8pyo0adR*.7kCMdnj',
            'avg' => ['r' => 12, 'g' => 34, 'b' => 56],
            'dc' => ['vibrant' => '#123456'],
            'classification' => [['name' => 'painting', 'confidence' => 0.98]],
            'detected_objects' => [['class_name' => 'person']],
            'exif' => ['Copyright' => 'Example Museum'],
            'iptc' => ['Credit' => 'Example Credit'],
            'xmp' => ['dc:rights' => 'Example Rights'],
        ]);

        self::assertSame(3000, $info->width);
        self::assertSame(2000, $info->height);
        self::assertSame(1, $info->orientation);
        self::assertSame('jpeg', $info->format);
        self::assertSame('image/jpeg', $info->mimeType);
        self::assertSame(123456, $info->size);
        self::assertSame('LEHV6nWB2yk8pyo0adR*.7kCMdnj', $info->blurhash);
        self::assertSame(['r' => 12, 'g' => 34, 'b' => 56], $info->average);
        self::assertSame(['vibrant' => '#123456'], $info->dominantColors);
        self::assertSame([['name' => 'painting', 'confidence' => 0.98]], $info->classification);
        self::assertSame([['class_name' => 'person']], $info->objects);
        self::assertSame(['Copyright' => 'Example Museum'], $info->embeddedMetadata->exif);
        self::assertSame(['Credit' => 'Example Credit'], $info->embeddedMetadata->iptc);
        self::assertSame(['dc:rights' => 'Example Rights'], $info->embeddedMetadata->xmp);
        self::assertSame('Example Rights', $info->embeddedMetadata->copyright());
        self::assertSame('Example Credit', $info->embeddedMetadata->credit());
    }

    public function testIiifHelpersDeriveStableValues(): void
    {
        $info = ImgproxyInfo::fromArray([
            'width' => 1024,
            'height' => 512,
            'format' => 'webp',
        ]);

        self::assertSame(2.0, $info->aspectRatio());
        self::assertSame('image/webp', $info->iiifFormat());
        self::assertSame(['width' => 1024, 'height' => 512], $info->sizePair());
    }

    public function testToArrayCanIncludeRawPayloadWhenRequested(): void
    {
        $payload = [
            'width' => 640,
            'height' => 480,
            'unknown_future_key' => ['kept' => true],
        ];

        $info = ImgproxyInfo::fromArray($payload);

        self::assertSame([
            'width' => 640,
            'height' => 480,
        ], $info->toArray());

        self::assertSame($payload, $info->toArray(includeRaw: true)['raw']);
    }

    public function testEmbeddedMetadataNormalizesCommonFieldsAcrossStandards(): void
    {
        $metadata = new ImgproxyEmbeddedMetadata(
            exif: ['Artist' => 'Fallback Artist'],
            iptc: ['Credit' => 'Example Credit'],
            xmp: [
                'dc:creator' => ['Example Creator'],
                'xmpRights:UsageTerms' => 'Example copyright terms',
                'xmpRights:WebStatement' => 'https://rightsstatements.org/vocab/InC/1.0/',
            ],
        );

        self::assertSame('Example copyright terms', $metadata->copyright());
        self::assertSame('Example Credit', $metadata->credit());
        self::assertSame('Example Creator', $metadata->creator());
        self::assertSame('https://rightsstatements.org/vocab/InC/1.0/', $metadata->rightsUri());
        self::assertSame([
            'copyright' => 'Example copyright terms',
            'credit' => 'Example Credit',
            'creator' => 'Example Creator',
            'rights_uri' => 'https://rightsstatements.org/vocab/InC/1.0/',
        ], $metadata->normalized());
    }
}
