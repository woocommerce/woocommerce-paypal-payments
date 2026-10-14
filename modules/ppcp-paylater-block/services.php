<?php

/**
 * The Pay Later block module services.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterBlock
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayLaterBlock;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\PayLaterBlock\PayLaterBlockRenderer;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('paylater-block.asset_getter' => static function (ContainerInterface $container): AssetGetter {
    $factory = $container->get('assets.asset_getter_factory');
    assert($factory instanceof AssetGetterFactory);
    return $factory->for_module('ppcp-paylater-block');
}, 'paylater-block.renderer' => static function (): PayLaterBlockRenderer {
    return new PayLaterBlockRenderer();
});
