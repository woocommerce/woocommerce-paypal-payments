<?php
/**
 * The Googlepay module.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class GooglepayModule
 */
class GooglepayModule implements ModuleInterface {
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
		$button = $c->get( 'googlepay.button' );
		assert( $button instanceof ButtonInterface );

		if ( ! $c->get( 'googlepay.eligible' ) ) {
			return;
		}

		$button->initialize();

		if ( ! $c->get( 'googlepay.available' ) ) {
			return;
		}

		add_action(
			'wp',
			static function () use ( $c, $button ) {
				if ( is_admin() ) {
					return;
				}
				$button->render();
			}
		);

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $c, $button ) {
				$button->enqueue();
			}
		);

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( PaymentMethodRegistry $payment_method_registry ) use ( $c, $button ): void {
				if ( $button->is_enabled() ) {
					$payment_method_registry->register( $c->get( 'googlepay.blocks-payment-method' ) );
				}
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
