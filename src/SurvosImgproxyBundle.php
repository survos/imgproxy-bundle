<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle;

use Survos\Kit\AbstractUxBundle;
use Survos\Kit\SurvosKitBundle;
use Survos\Kit\Traits\HasConfigurableRoutes;
use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

#[RequiredBundle(SurvosKitBundle::class)]
final class SurvosImgproxyBundle extends AbstractUxBundle
{
    use HasConfigurableRoutes;

    public const ASSET_PACKAGE = 'imgproxy';

    /**
     * Presets defined on the imgproxy server. The builder references these by
     * name (`preset:NAME`) so the server owns size/quality/format — every
     * resulting URL is short and canonical, which keeps the imgproxy/S3 cache
     * hot. The width/height/quality/format below are informational only (e.g.
     * for `<img>` sizing hints) and MUST be kept in sync with the imgproxy
     * server config:
     *
     *   tiny=rs:fit:200:200:0:0/q:70/f:webp
     *   thumb=rs:fit:400:400:0:0/q:80/f:webp
     *   observe=rs:fit:512:512:0:0/q:80/f:webp
     *   display=rs:fit:600:400:0:0/q:80/f:webp
     *   archive=rs:fit:3000:3000:0:0/q:88/f:webp
     */
    public const DEFAULT_PRESETS = [
        'tiny'    => ['width' => 200,  'height' => 200,  'resize' => 'fit', 'quality' => 70, 'format' => 'webp'],
        'thumb'   => ['width' => 400,  'height' => 400,  'resize' => 'fit', 'quality' => 80, 'format' => 'webp'],
        'observe' => ['width' => 512,  'height' => 512,  'resize' => 'fit', 'quality' => 80, 'format' => 'webp'],
        'display' => ['width' => 600,  'height' => 400,  'resize' => 'fit', 'quality' => 80, 'format' => 'webp'],
        'archive' => ['width' => 3000, 'height' => 3000, 'resize' => 'fit', 'quality' => 88, 'format' => 'webp'],
    ];

    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $this->addRouteOptions($children, '');

        $children
                ->scalarNode('host')->defaultValue('%env(default::IMGPROXY_HOST)%')->end()
                ->scalarNode('key')->defaultValue('%env(default::IMGPROXY_KEY)%')->end()
                ->scalarNode('salt')->defaultValue('%env(default::IMGPROXY_SALT)%')->end()
                ->arrayNode('presets')
                    ->useAttributeAsKey('name')
                    ->defaultValue(self::DEFAULT_PRESETS)
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('width')->isRequired()->end()
                            ->integerNode('height')->isRequired()->end()
                            ->scalarNode('resize')->defaultValue('fit')->end()
                            ->integerNode('quality')->defaultNull()->end()
                            ->scalarNode('format')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);

        $this->captureRouteConfig($config);
        $this->registerRouteLoader($builder);

        $container->services()
            ->set(ImgproxyUrlBuilder::class)
            ->arg('$host', $config['host'])
            ->arg('$key', $config['key'])
            ->arg('$salt', $config['salt'])
            ->arg('$presets', $config['presets'])
            ->arg('$httpClient', service('http_client')->nullOnInvalid())
            ->public()
            ->autoconfigure();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->addRouteLoaderCompilerPass($container);
    }
}
