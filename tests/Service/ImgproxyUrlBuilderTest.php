<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Survos\ImgproxyBundle\Dto\ImgproxyInfo;
use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ImgproxyUrlBuilderTest extends TestCase
{
    public function testResizePresetReferencesTheServerPresetByName(): void
    {
        $builder = new ImgproxyUrlBuilder(host: 'https://imgproxy.example');

        $url = $builder->resizePreset('https://images.example/full.jpg?download=1', 'thumb');

        self::assertSame(
            'https://imgproxy.example/insecure/pr:thumb/aHR0cHM6Ly9pbWFnZXMuZXhhbXBsZS9mdWxsLmpwZz9kb3dubG9hZD0x',
            $url,
        );
        self::assertSame($url, $builder->preset('https://images.example/full.jpg?download=1'), 'preset() is the contract name for the same call');
    }

    public function testInlineFormatOverrideIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ImgproxyUrlBuilder(host: 'https://imgproxy.example'))->preset('https://images.example/a.jpg', 'thumb', 'png');
    }

    public function testSourceUrlUnwrapsInfoAndLegacyPlainUrls(): void
    {
        $builder = new ImgproxyUrlBuilder(host: 'https://imgproxy.example');
        $source = 'https://images.example/a.jpg?x=1';

        self::assertSame($source, $builder->sourceUrl($builder->infoUrl($source, ['size'])));
        self::assertSame($source, $builder->sourceUrl($builder->resize($source, 100, 100)), 'the trailing @jpg is dropped');
        self::assertSame($source, $builder->sourceUrl('https://other.example/sig/rs:fit:1:1/plain/https://images.example/a.jpg%3Fx%3D1@webp'));
        self::assertSame('https://elsewhere.example/sig/pr:thumb/Zm9v', $builder->sourceUrl('https://elsewhere.example/sig/pr:thumb/Zm9v'));
    }

    public function testImageMetaAsksInfoForTheCommonSubset(): void
    {
        $requested = null;
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requested): MockResponse {
            $requested = $url;

            return new MockResponse(json_encode([
                'format' => 'jpeg', 'width' => 800, 'height' => 600,
                'blurhash' => 'L9MQ*JfQ-;t7~qt7xuxu9Foft7j[', 'thumb_hash' => '1B0806',
                'average' => ['R' => 108, 'G' => 108, 'B' => 255, 'A' => 255],
            ], JSON_THROW_ON_ERROR));
        });

        $meta = (new ImgproxyUrlBuilder(host: 'https://imgproxy.example', httpClient: $client))->imageMeta('https://images.example/a.jpg');

        self::assertStringContainsString('/format:1/dimensions:1/bh:4:3/th:1/avg:1/', (string) $requested);
        self::assertSame(['width' => 800, 'height' => 600, 'format' => 'jpeg', 'thumbhash' => base64_encode(hex2bin('1B0806')), 'blurhash' => 'L9MQ*JfQ-;t7~qt7xuxu9Foft7j[', 'averageColor' => '#6c6cff'], $meta->toArray());
    }

    public function testUnknownPresetThrowsInsteadOfGuessing(): void
    {
        $builder = new ImgproxyUrlBuilder(host: 'https://imgproxy.example');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown imgproxy preset "ai"');

        $builder->resizePreset('https://images.example/full.jpg', 'ai');
    }

    public function testFlickrSourcesSkipImgproxyForTheirOwnSizeVariant(): void
    {
        $builder = new ImgproxyUrlBuilder(host: 'https://imgproxy.example');

        self::assertSame(
            'https://live.staticflickr.com/2837/11107699045_17cc133806.jpg',
            $builder->resizePreset('https://farm3.staticflickr.com/2837/11107699045_17cc133806.jpg', 'thumb'),
        );
        self::assertSame(
            'https://live.staticflickr.com/3770/11180647874_8044e64b3a_m.jpg',
            $builder->resizePreset('https://farm4.staticflickr.com/3770/11180647874_8044e64b3a_b.jpg', 'tiny'),
        );
        self::assertSame(
            'https://live.staticflickr.com/3770/11180647874_8044e64b3a_b.jpg',
            $builder->resizePreset('https://farm4.staticflickr.com/3770/11180647874_8044e64b3a_b.jpg', 'archive'),
        );
        // never above the source: a 320 px upload has no _b
        self::assertSame(
            'https://live.staticflickr.com/3770/11180647874_8044e64b3a_n.jpg',
            $builder->resizePreset('https://live.staticflickr.com/3770/11180647874_8044e64b3a_n.jpg', 'archive'),
        );
        self::assertNull(ImgproxyUrlBuilder::flickrSize('https://www.flickr.com/photos/britishlibrary/11107699045', ['width' => 400, 'height' => 400]));
    }

    public function testInfoDtoMapsHttpResponseIntoTypedObject(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'width' => 1200,
                'height' => 800,
                'format' => 'png',
                'size' => 98765,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $builder = new ImgproxyUrlBuilder(
            host: 'https://imgproxy.example',
            httpClient: $client,
        );

        $info = $builder->infoDto('https://images.example/full.png', ['size', 'format', 'dimensions']);

        self::assertInstanceOf(ImgproxyInfo::class, $info);
        self::assertSame(1200, $info->width);
        self::assertSame(800, $info->height);
        self::assertSame('png', $info->format);
        self::assertSame(98765, $info->size);
        self::assertSame('image/png', $info->iiifFormat());
    }
}
