<?php
/**
 * Compatibility layer for subscriptions paid via PayPal Express Checkout.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PPEC
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\PPEC;

use Automattic\WooCommerce\Utilities\OrderUtil;
use stdClass;
use WooCommerce\PayPalCommerce\WcSubscriptions\RenewalHandler;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;

/**
 * Handles renewals and edit/display matters for subscriptions renewed via PayPal Express Checkout.
 */
class SubscriptionsHandler {

	const BILLING_AGREEMENT_TOKEN_TYPE = 'BILLING_AGREEMENT';

	/**
	 * PayPal Payments subscription renewal handler.
	 *
	 * @var RenewalHandler
	 */
	private $ppcp_renewal_handler;

	/**
	 * Mock gateway instance.
	 *
	 * @var MockGateway
	 */
	private $mock_gateway;


	/**
	 * Constructor.
	 *
	 * @param RenewalHandler $ppcp_renewal_handler PayPal Payments Subscriptions renewal handler.
	 * @param MockGateway    $gateway              Mock gateway instance.
	 */
	public function __construct( RenewalHandler $ppcp_renewal_handler, MockGateway $gateway ) {
		$this->ppcp_renewal_handler = $ppcp_renewal_handler;
		$this->mock_gateway         = $gateway;
	}

	/**
	 * Sets up hooks.
	 *
	 * @return void
	 */
	public function maybe_hook() {
		if ( ! PPECHelper::use_ppec_compat_layer_for_subscriptions() ) {
			return;
		}

		// "Mock" PPEC when needed.
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_mock_ppec_gateway' ) );

		// Add billing agreement as a valid token type.
		add_filter( 'woocommerce_paypal_payments_valid_payment_token_types', array( $this, 'add_billing_agreement_as_token_type' ) );

		// Process PPEC renewals through PayPal Payments.
		add_action( 'woocommerce_scheduled_subscription_payment_' . PPECHelper::PPEC_GATEWAY_ID, array( $this, 'process_renewal' ), 10, 2 );
	}

	/**
	 * Adds a mock gateway to disguise as PPEC when needed. Hooked onto `woocommerce_payment_gateways`.
	 * The mock gateway fixes display issues where subscriptions paid via PPEC appear as "via Manual Renewal" and also
	 * prevents subscriptions from automatically changing the payment method to "manual" when a subscription is edited.
	 *
	 * @param array $gateways List of gateways.
	 * @return array
	 */
	public function add_mock_ppec_gateway( $gateways ) {
		if ( ! isset( $gateways[ PPECHelper::PPEC_GATEWAY_ID ] ) && $this->should_mock_ppec_gateway() ) {
			$gateways[ PPECHelper::PPEC_GATEWAY_ID ] = $this->mock_gateway;
		}

		return $gateways;
	}

	/**
	 * Registers BILLING_AGREEMENT as a valid token type for using with the PayPal REST API.
	 *
	 * @param array $types List of token types.
	 * @return array
	 */
	public function add_billing_agreement_as_token_type( $types ) {
		if ( ! in_array( self::BILLING_AGREEMENT_TOKEN_TYPE, $types, true ) ) {
			$types[] = self::BILLING_AGREEMENT_TOKEN_TYPE;
		}

		return $types;
	}

	/**
	 * Processes subscription renewals on behalf of PayPal Express Checkout.
	 * Hooked onto `woocommerce_scheduled_subscription_payment_ppec_paypal`.
	 *
	 * @param float     $amount The order amount.
	 * @param \WC_Order $order  The renewal order.
	 * @return void
	 */
	public function process_renewal( $amount, $order ) {
		add_filter( 'woocommerce_paypal_payments_subscriptions_get_token_for_customer', array( $this, 'use_billing_agreement_as_token' ), 10, 3 );

		$this->ppcp_renewal_handler->renew( $order );

		remove_filter( 'woocommerce_paypal_payments_subscriptions_get_token_for_customer', array( $this, 'use_billing_agreement_as_token' ) );
	}

	/**
	 * Short-circuits `RenewalHandler::get_token_for_customer()` to use a Billing Agreement ID for PPEC orders
	 * instead of vaulted tokens.
	 *
	 * @param null|PaymentToken $token    Current token value.
	 * @param \WC_Customer      $customer Customer object.
	 * @param \WC_Order         $order    Renewal order.
	 * @return null|PaymentToken
	 */
	public function use_billing_agreement_as_token( $token, $customer, $order ) {
		if ( PPECHelper::PPEC_GATEWAY_ID === $order->get_payment_method() && wcs_order_contains_renewal( $order ) ) {
			$billing_agreement_id = $order->get_meta( '_ppec_billing_agreement_id', true );

			if ( $billing_agreement_id ) {
				$token = new PaymentToken( $billing_agreement_id, new stdClass(), 'BILLING_AGREEMENT' );
			}
		}

		return $token;
	}

	/**
	 * Checks whether the mock PPEC gateway should be used or not.
	 *
	 * @return bool
	 */
	private function should_mock_ppec_gateway() {
		// Are we processing a renewal?
		if ( doing_action( 'woocommerce_scheduled_subscription_payment' ) ) {
			return true;
		}

		// My Account > Subscriptions.
		if ( is_wc_endpoint_url( 'subscriptions' ) ) {
			return true;
		}

		// Checks that require Subscriptions.
		if ( class_exists( \WC_Subscriptions::class ) ) {
			// My Account > Subscriptions > (Subscription).
			if ( wcs_is_view_subscription_page() ) {
				$subscription = wcs_get_subscription( absint( get_query_var( 'view-subscription' ) ) );

				return ( $subscription && PPECHelper::PPEC_GATEWAY_ID === $subscription->get_payment_method() );
			}

			// Changing payment method?
			if ( is_wc_endpoint_url( 'order-pay' ) && isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$subscription = wcs_get_subscription( absint( get_query_var( 'order-pay' ) ) );

				return ( $subscription && PPECHelper::PPEC_GATEWAY_ID === $subscription->get_payment_method() );
			}

			// Early renew (via modal).
			if ( isset( $_GET['process_early_renewal'], $_GET['subscription_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$subscription = wcs_get_subscription( absint( $_GET['subscription_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				return ( $subscription && PPECHelper::PPEC_GATEWAY_ID === $subscription->get_payment_method() );
			}
		}

		// Admin-only from here onwards.
		if ( ! is_admin() ) {
			return false;
		}

		// Are we saving metadata for a subscription?
		if ( doing_action( 'woocommerce_process_shop_order_meta' ) ) {
			return true;
		}

		// Are we editing an order or subscription tied to PPEC?
		// phpcs:ignore WordPress.Security.NonceVerification
		$order_id = wc_clean( wp_unslash( $_GET['id'] ?? $_GET['post'] ?? $_POST['post_ID'] ?? '' ) );
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			return ( $order && PPECHelper::PPEC_GATEWAY_ID === $order->get_payment_method() );
		}

		// Are we on the WC > Subscriptions screen?
		/**
		 * Class exist in WooCommerce.
		 *
		 * @psalm-suppress UndefinedClass
		 */
		$post_type_or_page = class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled()
			// phpcs:ignore WordPress.Security.NonceVerification
			? wc_clean( wp_unslash( $_GET['page'] ?? '' ) )
			// phpcs:ignore WordPress.Security.NonceVerification
			: wc_clean( wp_unslash( $_GET['post_type'] ?? $_POST['post_type'] ?? '' ) );
		if ( $post_type_or_page === 'shop_subscription' || $post_type_or_page === 'wc-orders--shop_subscription' ) {
			return true;
		}

		return false;
	}
}
