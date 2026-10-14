<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle\Tests\Kernel;

use PHPUnit\Framework\TestCase;
use Survos\ImageContracts\ImageMetaProviderInterface;
use Survos\ImageContracts\ImageUrlBuilderInterface;
use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;
use Survos\ImgproxyBundle\SurvosImgproxyBundle;
use Survos\Kit\SurvosKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/** survos_imgproxy config → ImgproxyUrlBuilder, reachable through the image contracts and |imgproxy. */
final class BundleWiringTest extends TestCase
{
    public function testBuilderIsTheImageContractImplementation(): void
    {
        $kernel = new ImgproxyTestKernel('test', true);
        $kernel->boot();
        $c = $kernel->getContainer();

        $b = $c->get(ImgproxyUrlBuilder::class);
        self::assertSame('https://imgproxy.example', $b->host);
        self::assertSame($b, $c->get(ImageUrlBuilderInterface::class), 'consumers inject the contract, not the class');
        self::assertSame($b, $c->get(ImageMetaProviderInterface::class));

        $twig = $c->get('test.service_container')->get('twig');
        self::assertSame(
            $b->preset('https://images.example/a.jpg', 'tiny'),
            $twig->createTemplate("{{ 'https://images.example/a.jpg'|imgproxy('tiny') }}")->render(),
        );

        $kernel->shutdown();
    }
}

final class ImgproxyTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new SurvosKitBundle(), new SurvosImgproxyBundle()];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['test' => true, 'secret' => 'x', 'http_method_override' => false]);
        $container->extension('survos_imgproxy', ['host' => 'https://imgproxy.example']);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/imgproxy-bundle-test/'.spl_object_id($this);
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }
}
