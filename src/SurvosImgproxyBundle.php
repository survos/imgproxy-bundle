<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle;

use Survos\ImgproxyBundle\Command\ImgproxyUrlCommand;
use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SurvosImgproxyBundle extends AbstractBundle
{
    public const DEFAULT_PRESETS = [
        'ai'     => ['width' => 512,  'height' => 512,  'resize' => 'fit'],
        'thumb'  => ['width' => 300,  'height' => 300,  'resize' => 'fit'],
        'small'  => ['width' => 192,  'height' => 192,  'resize' => 'fit'],
        'medium' => ['width' => 600,  'height' => 400,  'resize' => 'fit'],
        'large'  => ['width' => 1200, 'height' => 800,  'resize' => 'fit'],
    ];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
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
        $container->services()
            ->set(ImgproxyUrlBuilder::class)
            ->arg('$host', $config['host'])
            ->arg('$key', $config['key'])
            ->arg('$salt', $config['salt'])
            ->arg('$presets', $config['presets'])
            ->public()
            ->autoconfigure();

        $container->services()
            ->set(ImgproxyUrlCommand::class)
            ->autowire()
            ->autoconfigure();
    }
}
