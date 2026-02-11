<?php

/**
 * The plugin module services.
 *
 * @package WooCommerce\PayPalCommerce
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce;

use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Http\RedirectorInterface;
use WooCommerce\PayPalCommerce\Http\WpRedirector;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Package;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Properties\Properties;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('ppcp.asset-version' => function (ContainerInterface $container): string {
    return $container->get('ppcp.plugin-version');
}, 'assets.asset_getter_factory' => function (ContainerInterface $container): AssetGetterFactory {
    $properties = $container->get(Package::PROPERTIES);
    assert($properties instanceof Properties);
    return new AssetGetterFactory((string) $properties->baseUrl(), $properties->basePath());
}, 'http.redirector' => function (ContainerInterface $container): RedirectorInterface {
    return new WpRedirector();
}, 'ppcp.plugin-version' => function (ContainerInterface $container): string {
    /** @var Properties $properties */
    $properties = $container->get(Package::PROPERTIES);
    return $properties->version();
}, 'ppcp.base-name' => function (ContainerInterface $container): string {
    /** @var Properties $properties */
    $properties = $container->get(Package::PROPERTIES);
    return $properties->baseName();
}, 'ppcp.path-to-plugin-folder' => function (ContainerInterface $container): string {
    /** @var Properties $properties */
    $properties = $container->get(Package::PROPERTIES);
    return $properties->basePath();
}, 'ppcp.path-to-plugin-main-file' => function (ContainerInterface $container): string {
    /** @var Properties $properties */
    $properties = $container->get(Package::PROPERTIES);
    /** @psalm-suppress UndefinedInterfaceMethod */
    return $properties->pluginMainFile();
});
