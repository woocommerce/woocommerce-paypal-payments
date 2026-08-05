<?php

/**
 * The SDK v6 module services.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Helper\RateLimiter;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('sdk-v6.asset-getter' => static function (ContainerInterface $container): AssetGetter {
    $factory = $container->get('assets.asset_getter_factory');
    assert($factory instanceof AssetGetterFactory);
    return $factory->for_module('ppcp-sdk-v6');
}, 'sdk-v6.manager' => static function (ContainerInterface $container): SdkV6Manager {
    return new SdkV6Manager($container->get('sdk-v6.asset-getter'), $container->get('ppcp.asset-version'), $container->get('settings.environment'));
}, 'sdk-v6.endpoint.client-token' => static function (ContainerInterface $container): ClientTokenEndpoint {
    return new ClientTokenEndpoint($container->get('button.request-data'), $container->get('woocommerce.logger.woocommerce'), $container->get('api.sdk-client-token'), $container->get('sdk-v6.rate-limiter'));
}, 'sdk-v6.rate-limiter' => static function (ContainerInterface $container): RateLimiter {
    return new RateLimiter('ppcp_sdk_v6_rl_', 10, 60);
});
