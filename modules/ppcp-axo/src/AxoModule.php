<?php
/**
 * The Axo module.
 *
 * @package WooCommerce\PayPalCommerce\Axo
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class AxoModule
 */
class AxoModule implements ModuleInterface {
	/**
	 * {@inheritDoc}
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ): void {

		add_filter(
			'woocommerce_payment_gateways',
			function ( $methods ): array {
				$methods[] = new AxoGateway();
				return $methods;
			},
			1,
			9
		);

		/**
		 * Param types removed to avoid third-party issues.
		 *
		 * @psalm-suppress MissingClosureParamType
		 */
		add_filter(
			'woocommerce_paypal_payments_sdk_components_hook',
			function( $components ) {
				$components[] = 'connect';
				return $components;
			}
		);

		add_action(
			'init',
			static function () use ( $c ) {

				// Enqueue frontend scripts.
				add_action(
					'wp_enqueue_scripts',
					static function () use ( $c ) {
						$module_url = $c->get( 'axo.url' );
						$version = '1';

						// Register styles.
						wp_register_style(
							'wc-ppcp-axo',
							untrailingslashit( $module_url ) . '/assets/css/styles.css',
							array(),
							$version
						);
						wp_enqueue_style( 'wc-ppcp-axo' );

						// Register scripts.
						wp_register_script(
							'wc-ppcp-axo',
							untrailingslashit( $module_url ) . '/assets/js/boot.js',
							array(),
							$version,
							true
						);
						wp_enqueue_script( 'wc-ppcp-axo' );

						wp_localize_script(
							'wc-ppcp-axo',
							'wc_ppcp_axo',
							array(
								// TODO
							)
						);

					}
				);

			},
			1
		);

	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}
}
