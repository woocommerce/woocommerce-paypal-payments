<?php
/**
 * The blocks module.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WooCommerce\PayPalCommerce\Blocks\Endpoint\UpdateShippingEndpoint;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class BlocksModule
 */
class BlocksModule implements ModuleInterface {
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
		if (
			! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' )
			|| ! function_exists( 'woocommerce_store_api_register_payment_requirements' )
		) {
			add_action(
				'admin_notices',
				function () {
					printf(
						'<div class="notice notice-error"><p>%1$s</p></div>',
						wp_kses_post(
							__(
								'PayPal checkout block initialization failed, possibly old WooCommerce version or disabled WooCommerce Blocks plugin.',
								'woocommerce-paypal-payments'
							)
						)
					);
				}
			);

			return;
		}

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( PaymentMethodRegistry $payment_method_registry ) use ( $c ): void {
				$payment_method_registry->register( $c->get( 'blocks.method' ) );
			}
		);

		woocommerce_store_api_register_payment_requirements(
			array(
				'data_callback' => function() use ( $c ): array {
					$smart_button = $c->get( 'button.smart-button' );
					assert( $smart_button instanceof SmartButtonInterface );

					if ( isset( $smart_button->script_data()['continuation'] ) ) {
						return array( 'ppcp_continuation' );
					}

					return array();
				},
			)
		);

		add_action(
			'wc_ajax_' . UpdateShippingEndpoint::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'blocks.endpoint.update-shipping' );
				assert( $endpoint instanceof UpdateShippingEndpoint );

				$endpoint->handle_request();
			}
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
