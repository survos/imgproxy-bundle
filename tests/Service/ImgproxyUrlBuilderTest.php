<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Tests\Service;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Survos\ImgproxyBundle\Dto\ImgproxyInfo;
use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ImgproxyUrlBuilderTest extends TestCase
{
    public function testResizePresetBuildsCanonicalInlinePresetUrl(): void
    {
        $builder = new ImgproxyUrlBuilder(host: 'https://imgproxy.example');

        $url = $builder->resizePreset('https://images.example/full.jpg?download=1', 'thumb');

        self::assertSame(
            'https://imgproxy.example/insecure/rs:fit:400:400:0:0/q:80/f:webp/plain/https://images.example/full.jpg%3Fdownload%3D1',
            $url,
        );
    }

    public function testUnknownPresetThrowsInsteadOfGuessing(): void
    {
        $builder = new ImgproxyUrlBuilder(host: 'https://imgproxy.example');

        $this->expectException(InvalidArgumentException::class);
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
