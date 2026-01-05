<?php
/**
 * The Axo module services.
 *
 * @package WooCommerce\PayPalCommerce\Axo
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AxoBlock;

use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	// If AXO Block is configured and onboarded.
	'axoblock.available'            => static function ( ContainerInterface $container ): bool {
		return true;
	},
	'axoblock.get_module_asset_url' => static function ( ContainerInterface $container ): callable {
		/** @var $getter callable(string, string):string */
		$getter = $container->get( 'assets.get_module_asset_url' );

		return static function ( string $asset_name ) use ( $getter ): string {
			return ( $getter )( 'ppcp-axo-block', $asset_name );
		};
	},
	'axoblock.method'               => static function ( ContainerInterface $container ): AxoBlockPaymentMethod {
		return new AxoBlockPaymentMethod(
			$container->get( 'axoblock.get_module_asset_url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'axo.gateway' ),
			fn(): SmartButtonInterface => $container->get( 'button.smart-button' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wcgateway.configuration.card-configuration' ),
			$container->get( 'settings.environment' ),
			$container->get( 'wcgateway.url' ),
			$container->get( 'axo.payment_method_selected_map' ),
			$container->get( 'axo.supported-country-card-type-matrix' )
		);
	},
);
