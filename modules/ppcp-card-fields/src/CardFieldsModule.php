<?php
/**
 * The Card Fields module.
 *
 * @package WooCommerce\PayPalCommerce\CardFields
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\CardFields;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

class CardFieldsModule implements ModuleInterface {

	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	public function run(ContainerInterface $c): void {
		if ( ! $c->get( 'card-fields.eligible' ) ) {
			return;
		}

		add_action(
			'wp_enqueue_scripts',
			function () use ($c) {
				$module_url = $c->get( 'card-fields.module.url' );
				wp_enqueue_script(
					'ppcp-card-fields-boot',
					untrailingslashit( $module_url ) . '/assets/js/boot.js',
					array( 'jquery' ),
					$c->get( 'ppcp.asset-version' ),
					true
				);
			}
		);
	}
}
