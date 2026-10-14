<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Tests\Contract;

use Survos\ImageContracts\ImageUrlBuilderInterface;
use Survos\ImageContracts\Test\ImageUrlBuilderTestCase;
use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;

final class ImgproxyImageUrlBuilderTest extends ImageUrlBuilderTestCase
{
    protected function createBuilder(): ImageUrlBuilderInterface
    {
        return new ImgproxyUrlBuilder('https://imgproxy.example', str_repeat('01', 32), str_repeat('02', 32));
    }

    protected function createUnconfiguredBuilder(): ImageUrlBuilderInterface
    {
        return new ImgproxyUrlBuilder();
    }

    protected function unsupportedSource(): string
    {
        return 'ftp://example.com/a.jpg';
    }

    public function testObjectStorageSchemesAreSupported(): void
    {
        $builder = $this->createBuilder();
        foreach (['s3://bucket/a.jpg', 'gs://bucket/a.jpg', 'abs://container/a.jpg', 'swift://container/a.jpg'] as $source) {
            self::assertTrue($builder->supports($source), $source);
            self::assertSame($source, $builder->sourceUrl($builder->preset($source)));
        }
        self::assertFalse($builder->supports('/relative/a.jpg'));
    }
}
