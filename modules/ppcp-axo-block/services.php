<?php

/**
 * The Axo module services.
 *
 * @package WooCommerce\PayPalCommerce\Axo
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AxoBlock;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array(
    // If AXO Block is configured and onboarded.
    'axoblock.available' => static function (ContainerInterface $container): bool {
        return \true;
    },
    'axoblock.asset_getter' => static function (ContainerInterface $container): AssetGetter {
        $factory = $container->get('assets.asset_getter_factory');
        assert($factory instanceof AssetGetterFactory);
        return $factory->for_module('ppcp-axo-block');
    },
    'axoblock.method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\AxoBlock\AxoBlockPaymentMethod {
        return new \WooCommerce\PayPalCommerce\AxoBlock\AxoBlockPaymentMethod($container->get('axoblock.asset_getter'), $container->get('ppcp.asset-version'), $container->get('axo.gateway'), fn(): SmartButtonInterface => $container->get('button.smart-button'), $container->get('wcgateway.settings'), $container->get('wcgateway.configuration.card-configuration'), $container->get('settings.environment'), $container->get('wcgateway.asset_getter'), $container->get('axo.payment_method_selected_map'), $container->get('axo.supported-country-card-type-matrix'));
    },
);
