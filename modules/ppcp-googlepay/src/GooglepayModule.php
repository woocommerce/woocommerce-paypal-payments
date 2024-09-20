<?php
/**
 * The Googlepay module.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Googlepay\Endpoint\UpdatePaymentDataEndpoint;
use WooCommerce\PayPalCommerce\Googlepay\Helper\ApmProductStatus;
use WooCommerce\PayPalCommerce\Googlepay\Helper\AvailabilityNotice;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class GooglepayModule
 */
class GooglepayModule implements ServiceModule, ExtendingModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * {@inheritDoc}
	 */
	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function extensions(): array {
		return require __DIR__ . '/../extensions.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ): bool {

		// Clears product status when appropriate.
		add_action(
			'woocommerce_paypal_payments_clear_apm_product_status',
			function( Settings $settings = null ) use ( $c ): void {
				$apm_status = $c->get( 'googlepay.helpers.apm-product-status' );
				assert( $apm_status instanceof ApmProductStatus );
				$apm_status->clear( $settings );
			}
		);

		add_action(
			'init',
			static function () use ( $c ) {

				// Check if the module is applicable, correct country, currency, ... etc.
				if ( ! $c->get( 'googlepay.eligible' ) ) {
					return;
				}

				// Load the button handler.
				$button = $c->get( 'googlepay.button' );
				assert( $button instanceof ButtonInterface );
				$button->initialize();

				// Show notice if there are product availability issues.
				$availability_notice = $c->get( 'googlepay.availability_notice' );
				assert( $availability_notice instanceof AvailabilityNotice );
				$availability_notice->execute();

				// Check if this merchant can activate / use the buttons.
				// We allow non referral merchants as they can potentially still use GooglePay, we just have no way of checking the capability.
				if ( ( ! $c->get( 'googlepay.available' ) ) && $c->get( 'googlepay.is_referral' ) ) {
					return;
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
						$smart_button = $c->get( 'button.smart-button' );
						assert( $smart_button instanceof SmartButtonInterface );

						if ( $smart_button->should_load_ppcp_script() ) {
							$button->enqueue();
							return;
						}

						/*
						 * Checkout page, but no PPCP scripts were loaded. Most likely in continuation mode.
						 * Need to enqueue some Google Pay scripts to populate the billing form with details
						 * provided by Google Pay.
						 */
						if ( is_checkout() ) {
							$button->enqueue();
						}

						if ( has_block( 'woocommerce/checkout' ) || has_block( 'woocommerce/cart' ) ) {
							/**
							 * Should add this to the ButtonInterface.
							 *
							 * @psalm-suppress UndefinedInterfaceMethod
							 */
							$button->enqueue_styles();
						}
					}
				);

				// Enqueue backend scripts.
				add_action(
					'admin_enqueue_scripts',
					static function () use ( $c, $button ) {
						if ( ! is_admin() || ! $c->get( 'wcgateway.is-ppcp-settings-payment-methods-page' ) ) {
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
					function( array $settings ) use ( $c ): array {
						if ( is_array( $settings['components'] ) ) {
							$settings['components'][] = 'googlepay';
						}
						return $settings;
					}
				);

				// Initialize AJAX endpoints.
				add_action(
					'wc_ajax_' . UpdatePaymentDataEndpoint::ENDPOINT,
					static function () use ( $c ) {
						$endpoint = $c->get( 'googlepay.endpoint.update-payment-data' );
						assert( $endpoint instanceof UpdatePaymentDataEndpoint );
						$endpoint->handle_request();
					}
				);

			},
			1
		);

		add_filter(
			'woocommerce_payment_gateways',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			static function ( $methods ) use ( $c ): array {
				if ( ! is_array( $methods ) ) {
					return $methods;
				}

				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				if ( $settings->has( 'googlepay_button_enabled' ) && $settings->get( 'googlepay_button_enabled' ) ) {
					$googlepay_gateway = $c->get( 'googlepay.wc-gateway' );
					assert( $googlepay_gateway instanceof WC_Payment_Gateway );

					$methods[] = $googlepay_gateway;
				}

				return $methods;
			}
		);

		add_action(
			'woocommerce_review_order_after_submit',
			function () {
				echo '<div id="ppc-button-' . esc_attr( GooglePayGateway::ID ) . '"></div>';
			}
		);

		add_action(
			'woocommerce_pay_order_after_submit',
			function () {
				echo '<div id="ppc-button-' . esc_attr( GooglePayGateway::ID ) . '"></div>';
			}
		);

		return true;
	}
}
