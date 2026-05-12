<?php

declare(strict_types=1);

namespace Survos\ImgproxyBundle;

use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SurvosImgproxyBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('host')->defaultNull()->end()
                ->scalarNode('key')->defaultValue('%env(default::IMGPROXY_KEY)%')->end()
                ->scalarNode('salt')->defaultValue('%env(default::IMGPROXY_SALT)%')->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->set(ImgproxyUrlBuilder::class)
            ->arg('$host', $config['host'])
            ->arg('$key', $config['key'])
            ->arg('$salt', $config['salt'])
            ->public();
    }
}
