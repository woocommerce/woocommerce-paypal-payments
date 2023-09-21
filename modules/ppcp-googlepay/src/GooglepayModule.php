<?php
/**
 * The Googlepay module.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\Googlepay\Helper\ApmProductStatus;
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

		if ( ! $c->get( 'googlepay.eligible' ) ) {
			return;
		}

		$button = $c->get( 'googlepay.button' );
		assert( $button instanceof ButtonInterface );
		$button->initialize();

		if ( ! $c->get( 'googlepay.available' ) ) {

			$apm_status = $c->get( 'googlepay.helpers.apm-product-status' );
			assert( $apm_status instanceof ApmProductStatus );

			// TODO: refactor the notices.
			if ( $apm_status->has_request_failure() ) {

				add_filter(
					Repository::NOTICES_FILTER,
					/**
					 * Adds seller status notice.
					 *
					 * @param array $notices The notices.
					 * @return array
					 *
					 * @psalm-suppress MissingClosureParamType
					 */
					static function ( $notices ) use ( $c ): array {

						$message = sprintf(
							__(
								'<p>There was an error getting your PayPal seller status. Some features may be disabled.</p><p>Certify that you connected to your account via our onboarding process.</p>',
								'woocommerce-paypal-payments'
							)
						);

						// Name the key so it can be overridden.
						$notices['error_product_status'] = new Message( $message, 'error', true, 'ppcp-notice-wrapper' );
						return $notices;
					}
				);

			} else {

				add_filter(
					Repository::NOTICES_FILTER,
					/**
					 * Adds GooglePay not available notice.
					 *
					 * @param array $notices The notices.
					 * @return array
					 *
					 * @psalm-suppress MissingClosureParamType
					 */
					static function ( $notices ) use ( $c ): array {

						$message = sprintf(
							__(
								'Google Pay is not available on your PayPal account.',
								'woocommerce-paypal-payments'
							)
						);

						$notices[] = new Message( $message, 'warning', true, 'ppcp-notice-wrapper' );
						return $notices;
					}
				);

			}

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

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( PaymentMethodRegistry $payment_method_registry ) use ( $c, $button ): void {
				if ( $button->is_enabled() ) {
					$payment_method_registry->register( $c->get( 'googlepay.blocks-payment-method' ) );
				}
			}
		);

		add_action(
			'woocommerce_paypal_payments_admin_gateway_settings',
			function( array $settings ) use ( $c, $button ): array {
				if ( is_array( $settings['components'] ) ) {
					$settings['components'][] = 'googlepay';
				}
				return $settings;
			}
		);

		// Clear product status handling.
		add_action(
			'woocommerce_paypal_payments_clear_apm_product_status',
			function( Settings $settings = null ) use ( $c ): void {
				$apm_status = $c->get( 'googlepay.helpers.apm-product-status' );
				assert( $apm_status instanceof ApmProductStatus );

				if ( ! $settings instanceof Settings ) {
					$settings = null;
				}

				$apm_status->clear( $settings );
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
