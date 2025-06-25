<?php
/**
 * The local alternative payment methods module.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WC_Order;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\FeesUpdater;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class LocalAlternativePaymentMethodsModule
 */
class LocalAlternativePaymentMethodsModule implements ServiceModule, ExtendingModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * {@inheritDoc}
	 */
	public function services() : array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function extensions() : array {
		return require __DIR__ . '/../extensions.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ) : bool {
		add_action( 'after_setup_theme', fn() => $this->run_with_translations( $c ) );

		return true;
	}

	/**
	 * Set up WP hooks that depend on translation features.
	 * Runs after the theme setup, when translations are available, which is fired
	 * before the `init` hook, which usually contains most of the logic.
	 *
	 * @param ContainerInterface $c The DI container.
	 * @return void
	 */
	private function run_with_translations( ContainerInterface $c ) : void {
		// When Local APMs are disabled, none of the following hooks are needed.
		if ( ! $this->should_add_local_apm_gateways( $c ) ) {
			return;
		}

		/**
		 * The "woocommerce_payment_gateways" filter is responsible for ADDING
		 * custom payment gateways to WooCommerce. Here, we add all the local
		 * APM gateways to the filtered list, so they become available later on.
		 */
		add_filter(
			'woocommerce_payment_gateways',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $methods ) use ( $c ) {
				if ( ! is_array( $methods ) ) {
					return $methods;
				}

				$payment_methods = $c->get( 'ppcp-local-apms.payment-methods' );
				foreach ( $payment_methods as $key => $value ) {
					$methods[] = $c->get( 'ppcp-local-apms.' . $key . '.wc-gateway' );
				}

				return $methods;
			}
		);

		/**
		 * Filters the "available gateways" list by REMOVING gateways that
		 * are not available for the current customer.
		 */
		add_filter(
			'woocommerce_available_payment_gateways',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $methods ) use ( $c ) {
				if ( ! is_array( $methods ) || is_admin() || empty( WC()->customer ) ) {
					// Don't restrict the gateway list on wp-admin or when no customer is known.
					return $methods;
				}

				$payment_methods  = $c->get( 'ppcp-local-apms.payment-methods' );
				$customer_country = WC()->customer->get_billing_country() ?: WC()->customer->get_shipping_country();
				$site_currency    = get_woocommerce_currency();

				// Remove unsupported gateways from the customer's payment options.
				foreach ( $payment_methods as $payment_method ) {
					$is_currency_supported = in_array( $site_currency, $payment_method['currencies'], true );
					$is_country_supported  = in_array( $customer_country, $payment_method['countries'], true );

					if ( ! $is_currency_supported || ! $is_country_supported ) {
						unset( $methods[ $payment_method['id'] ] );
					}
				}

				return $methods;
			}
		);

		/**
		 * Adds all local APM gateways in the "payment_method_type" block registry
		 * to make the payment methods available in the Block Checkout.
		 *
		 * @see IntegrationRegistry::initialize
		 */
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( PaymentMethodRegistry $payment_method_registry ) use ( $c ): void {
				$payment_methods = $c->get( 'ppcp-local-apms.payment-methods' );
				foreach ( $payment_methods as $key => $value ) {
					$payment_method_registry->register( $c->get( 'ppcp-local-apms.' . $key . '.payment-method' ) );
				}
			}
		);

		add_filter(
			'woocommerce_paypal_payments_localized_script_data',
			function ( array $data ) use ( $c ) {
				$payment_methods = $c->get( 'ppcp-local-apms.payment-methods' );

				$default_disable_funding               = $data['url_params']['disable-funding'] ?? '';
				$disable_funding                       = array_merge( array_keys( $payment_methods ), array_filter( explode( ',', $default_disable_funding ) ) );
				$data['url_params']['disable-funding'] = implode( ',', array_unique( $disable_funding ) );

				return $data;
			}
		);

		add_action(
			'woocommerce_before_thankyou',
			/**
			 * Activate is_checkout() on woocommerce/classic-shortcode checkout blocks.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $order_id ) use ( $c ) {
				$order = wc_get_order( $order_id );
				if ( ! $order instanceof WC_Order ) {
					return;
				}

				// phpcs:disable WordPress.Security.NonceVerification.Recommended
				$cancelled = wc_clean( wp_unslash( $_GET['cancelled'] ?? '' ) );
				$order_key = wc_clean( wp_unslash( $_GET['key'] ?? '' ) );
				// phpcs:enable

				$payment_methods = $c->get( 'ppcp-local-apms.payment-methods' );
				if (
					! $this->is_local_apm( $order->get_payment_method(), $payment_methods )
					|| ! $cancelled
					|| $order->get_order_key() !== $order_key
				) {
					return;
				}

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$error_code = wc_clean( wp_unslash( $_GET['errorcode'] ?? '' ) );
				if ( $error_code === 'processing_error' || $error_code === 'payment_error' ) {
					$order->update_status( 'failed', __( "The payment can't be processed because of an error.", 'woocommerce-paypal-payments' ) );

					add_filter( 'woocommerce_order_has_status', '__return_true' );
				}
			}
		);

		add_action(
			'woocommerce_paypal_payments_payment_capture_completed_webhook_handler',
			function( WC_Order $wc_order, string $order_id ) use ( $c ) {
				$payment_methods = $c->get( 'ppcp-local-apms.payment-methods' );
				if (
				! $this->is_local_apm( $wc_order->get_payment_method(), $payment_methods )
				) {
					return;
				}

				$fees_updater = $c->get( 'wcgateway.helper.fees-updater' );
				assert( $fees_updater instanceof FeesUpdater );

				$fees_updater->update( $order_id, $wc_order );
			},
			10,
			2
		);
	}

	/**
	 * Check if given payment method is a local APM.
	 *
	 * @param string $selected_payment_method Selected payment method.
	 * @param array  $payment_methods Available local APMs.
	 * @return bool
	 */
	private function is_local_apm( string $selected_payment_method, array $payment_methods ): bool {
		foreach ( $payment_methods as $payment_method ) {
			if ( $payment_method['id'] === $selected_payment_method ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the local APMs should be added to the available payment gateways.
	 *
	 * @param ContainerInterface $container Container.
	 * @return bool
	 */
	private function should_add_local_apm_gateways( ContainerInterface $container ) : bool {
		// APMs are only available after merchant onboarding is completed.
		$is_connected = $container->get( 'settings.flag.is-connected' );
		if ( ! $is_connected ) {
			/**
			 * When the merchant is _not_ connected yet, we still need to
			 * register the APM gateways in one case:
			 *
			 * During the authentication process (which happens via a REST call)
			 * the gateways need to be present, so they can be correctly
			 * pre-configured for new merchants.
			 */
			return $this->is_rest_request();
		}

		// The general plugin functionality must be enabled.
		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );
		if ( ! $settings->has( 'enabled' ) || ! $settings->get( 'enabled' ) ) {
			return false;
		}

		// Register APM gateways, when the relevant setting is active.
		return $settings->has( 'allow_local_apm_gateways' )
			&& $settings->get( 'allow_local_apm_gateways' ) === true;
	}

	/**
	 * Checks, whether the current request is trying to access a WooCommerce REST endpoint.
	 *
	 * @return bool True, if the request path matches the WC-Rest namespace.
	 */
	private function is_rest_request(): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );

		return str_contains( $request_uri, '/wp-json/wc/' );
	}
}
