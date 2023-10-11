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
use WooCommerce\PayPalCommerce\Googlepay\Helper\ApmProductStatus;
use WooCommerce\PayPalCommerce\Googlepay\Helper\AvailabilityNotice;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

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

		// Clears product status when appropriate.
		add_action(
			'woocommerce_paypal_payments_clear_apm_product_status',
			function( Settings $settings = null ) use ( $c ): void {
				$apm_status = $c->get( 'googlepay.helpers.apm-product-status' );
				assert( $apm_status instanceof ApmProductStatus );

				$apm_status->clear( $settings );
			}
		);

		// Check if the module is applicable, correct country, currency, ... etc.
		if ( ! $c->get( 'googlepay.eligible' ) ) {
			return;
		}

		// Load the button handler.
		$button = $c->get( 'googlepay.button' );
		assert( $button instanceof ButtonInterface );
		$button->initialize();

		// Check if this merchant can activate / use the buttons.
		// We allow non referral merchants as they can potentially still use GooglePay, we just have no way of checking the capability.
		if ( ( ! $c->get( 'googlepay.available' ) ) && $c->get( 'googlepay.is_referral' ) ) {
			$availability_notice = $c->get( 'googlepay.availability_notice' );
			assert( $availability_notice instanceof AvailabilityNotice );
			$availability_notice->execute();
			return;
		}

		// Show notice and continue if merchant isn't onboarded via a referral.
		if ( ! $c->get( 'googlepay.is_referral' ) ) {
			$availability_notice = $c->get( 'googlepay.availability_notice' );
			assert( $availability_notice instanceof AvailabilityNotice );
			$availability_notice->execute();
		}

		// Initializes button rendering.
		add_action(
			'wp',
			static function () use ( $c, $button ) {
				if ( is_admin() ) {
					return;
				}
				$button->render();
			}
		);

		// Enqueue frontend scripts.
		add_action(
			'wp_enqueue_scripts',
			static function () use ( $c, $button ) {
				$button->enqueue();
			}
		);

		// Enqueue backend scripts.
		add_action(
			'admin_enqueue_scripts',
			static function () use ( $c, $button ) {
				if ( ! is_admin() ) {
					return;
				}
				/**
				 * Should add this to the ButtonInterface.
				 *
				 * @psalm-suppress UndefinedInterfaceMethod
				 */
				$button->enqueue_admin();
			}
		);

		// Registers buttons on blocks pages.
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( PaymentMethodRegistry $payment_method_registry ) use ( $c, $button ): void {
				if ( $button->is_enabled() ) {
					$payment_method_registry->register( $c->get( 'googlepay.blocks-payment-method' ) );
				}
			}
		);

		// Adds GooglePay component to the backend button preview settings.
		add_action(
			'woocommerce_paypal_payments_admin_gateway_settings',
			function( array $settings ) use ( $c, $button ): array {
				if ( is_array( $settings['components'] ) ) {
					$settings['components'][] = 'googlepay';
				}
				return $settings;
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
