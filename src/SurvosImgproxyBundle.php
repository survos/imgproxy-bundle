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

#[RequiredBundle(SurvosKitBundle::class)]
final class SurvosImgproxyBundle extends AbstractUxBundle
{
    use HasConfigurableRoutes;

    public const ASSET_PACKAGE = 'imgproxy';

    public const DEFAULT_PRESETS = [
        'ai'          => ['width' => 512,  'height' => 512,  'resize' => 'fit'],
        'ai_thumbnail'=> ['width' => 512,  'height' => 512,  'resize' => 'fit'],
        'ai_hires'    => ['width' => 2048, 'height' => 2048, 'resize' => 'fit'],
        'thumb'       => ['width' => 300,  'height' => 300,  'resize' => 'fit'],
        'small'       => ['width' => 192,  'height' => 192,  'resize' => 'fit'],
        'medium'      => ['width' => 600,  'height' => 400,  'resize' => 'fit'],
        'large'       => ['width' => 1600, 'height' => 1600, 'resize' => 'fit'],
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
            ->public()
            ->autoconfigure();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->addRouteLoaderCompilerPass($container);
    }
}
