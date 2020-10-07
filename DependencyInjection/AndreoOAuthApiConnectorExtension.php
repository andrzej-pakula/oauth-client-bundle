<?php

declare(strict_types=1);

namespace Andreo\OAuthApiConnectorBundle\DependencyInjection;

use Andreo\GuzzleBundle\Configurator\ConfigProviderInterface;
use Andreo\OAuthApiConnectorBundle\Client\Attribute\Zone;
use Andreo\OAuthApiConnectorBundle\Client\Attribute\ZoneId;
use Andreo\OAuthApiConnectorBundle\Client\Client;
use Andreo\OAuthApiConnectorBundle\Client\ClientFactoryInterface;
use Andreo\OAuthApiConnectorBundle\Client\ClientInterface;
use Andreo\OAuthApiConnectorBundle\Client\MetaDataProviderInterface;
use Andreo\OAuthApiConnectorBundle\Http\Provider\ApiConfigProviderFactory;
use Andreo\OAuthApiConnectorBundle\Middleware\MiddlewareInterface;
use Andreo\OAuthApiConnectorBundle\Middleware\SuccessfulResponseMiddleware;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;


class AndreoOAuthApiConnectorExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);

        foreach ($config as $type => $options) {
            foreach ($options['clients'] ?? [] as $name => $config) {
                $version = $config['version'] ?? null;
                $clientDef = (new Definition(Client::class))
                    ->setFactory(new Reference(ClientFactoryInterface::class))
                    ->setArguments([
                        [
                            'client_id' => $config['auth']['id'],
                            'client_name' => $name,
                            'client_secret' => $config['auth']['secret'],
                            'type' => $type,
                            'version' => $version
                        ],
                        new TaggedIteratorArgument('andreo.oauth_client.middleware'),
                    ])
                    ->addTag('andreo.oauth.client', [
                        'name' => $name,
                        'type' => $type,
                        'version' => $version
                    ]);

                $container->setDefinition(ClientInterface::class . '_' . $name, $clientDef);

                $zones = [];
                foreach ($config['zones'] ?? [] as $id => $zoneConfig) {
                    $zones[] = [
                        'zone_id' => $id,
                        'successful_response_uri' => $zoneConfig['successful_response_uri']
                    ];
                }

                if (!empty($zones)) {
                    $successfulResponseMiddlewareDef = (new Definition(SuccessfulResponseMiddleware::class))
                        ->setFactory([new Reference(SuccessfulResponseMiddleware::class), 'withZonesConfigs'])
                        ->addArgument($zones)
                        ->addTag('andreo.oauth_client.middleware', [
                            'client' => $name,
                            'priority' => 100
                        ]);

                    $container->setDefinition(SuccessfulResponseMiddleware::class . '_' . $name, $successfulResponseMiddlewareDef);
                }
            }
        }

        $this->registerAutoconfiguration($container);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!isset($bundles['AndreoGuzzleBundle'])) {
            throw new RuntimeException('');
        }

        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);

        $guzzleClientConfigs = [];
        foreach ($config as $type => $options) {
            foreach ($options['clients'] ?? [] as $name => $config) {
                $guzzleConfigProviderDef = (new Definition(ConfigProviderInterface::class))
                    ->setFactory(new Reference(ApiConfigProviderFactory::class))
                    ->setArguments([
                        $type,
                        $client['ver'] ?? null
                    ]);
                $guzzleConfigProviderId = "andreo.guzzle.oauth.client.$name.config_provider";
                $container->setDefinition($guzzleConfigProviderId, $guzzleConfigProviderDef);

                $httpClientDef = (new Definition($config['http_client_id']));
                $httpClientId = "andreo.oauth.http_client_$name";
                $container->setDefinition($httpClientId, $httpClientDef);

                $guzzleClientConfigs['clients'][$name] = [
                    'config_provider_id' => $guzzleConfigProviderId,
                    'decorator_id' => $httpClientId
                ];
            }
        }

        $container->prependExtensionConfig('andreo_guzzle', $guzzleClientConfigs);
    }

    private function registerAutoconfiguration(ContainerBuilder $container): void
    {
        $container
            ->registerForAutoconfiguration(MetaDataProviderInterface::class)
            ->addTag('andreo.oauth_client.meta_data_provider');

        $container
            ->registerForAutoconfiguration(MiddlewareInterface::class)
            ->addTag('andreo.oauth_client.middleware');
    }

}
