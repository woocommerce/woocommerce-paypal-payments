<?php
/**
 * The Pay Later block module services.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterBlock
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterBlock;

use WooCommerce\PayPalCommerce\PayLaterBlock\PayLaterBlockRenderer;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'paylater-block.get_module_asset_url' => static function ( ContainerInterface $container ): callable {
		/** @var $getter callable(string, string):string */
		$getter = $container->get( 'assets.get_module_asset_url' );

		return static function ( string $asset_name ) use ( $getter ): string {
			return ( $getter )( 'ppcp-paylater-block', $asset_name );
		};
	},
	'paylater-block.renderer'             => static function (): PayLaterBlockRenderer {
		return new PayLaterBlockRenderer();
	},
);
