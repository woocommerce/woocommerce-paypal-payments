<?php
/**
 * Agentic Checkout Processor.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Helper;

use WC_Order;
use WP_Error;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order as PayPalOrder;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PaymentMethod;

/**
 * Orchestrates the complete checkout workflow for Agentic Commerce.
 *
 * This service coordinates the following steps:
 * - Fetches PayPal order
 * - Translates PayPalCart to CartData
 * - Creates WooCommerce order
 * - Links PayPal and WC orders
 * - Captures payment
 */
class AgenticCheckoutProcessor {

	private PayPalOrderManager $order_manager;

	private WooCommerceOrderCreator $wc_order_creator;

	private CartTransformer $cart_transformer;

	public function __construct(
		PayPalOrderManager $order_manager,
		WooCommerceOrderCreator $wc_order_creator,
		CartTransformer $cart_transformer
	) {

		$this->order_manager    = $order_manager;
		$this->wc_order_creator = $wc_order_creator;
		$this->cart_transformer = $cart_transformer;
	}

	/**
	 * Process agentic checkout: translate cart, create order, capture payment.
	 *
	 * This orchestrates the complete checkout workflow:
	 * 1. Fetches the PayPal Order using the token (order ID)
	 * 2. Translates PayPalCart to CartData
	 * 3. Creates WooCommerce order from PayPal Order and CartData
	 * 4. Links PayPal order with WC order ID
	 * 5. Captures the PayPal payment
	 *
	 * @param PayPalCart    $cart            The PayPal cart data.
	 * @param PaymentMethod $payment_method  The payment method data.
	 * @param string        $paypal_order_id The PayPal Order ID (ec_token).
	 * @return WC_Order|WP_Error The created order or error.
	 */
	public function process(
		PayPalCart $cart,
		PaymentMethod $payment_method,
		string $paypal_order_id
	) {

		try {
			// Step 1: Fetch PayPal order.
			$paypal_order = $this->order_manager->fetch_order( $paypal_order_id );

			// Step 2: Transform PayPalCart to CartData (skipping invalid products).
			$cart_data = $this->cart_transformer->paypal_cart_to_wc_cart( $cart );

			// Step 3: Create WC order with customer data from PayPalCart.
			$wc_order = $this->create_order( $paypal_order, $cart_data, $cart, $payment_method, $paypal_order_id );
			if ( is_wp_error( $wc_order ) ) {
				return $wc_order;
			}

			// Step 4: Link PayPal order with WC order ID.
			$this->link_orders( $paypal_order_id, $wc_order );

			// Step 5: Capture payment.
			$this->capture_payment( $paypal_order, $wc_order, $paypal_order_id );

			return $wc_order;

		} catch ( \Exception $e ) {
			return new WP_Error( 'order_creation_failed', $e->getMessage() );
		}
	}

	/**
	 * Create WooCommerce order from PayPal order and cart data.
	 *
	 * @param PayPalOrder   $paypal_order    The PayPal order object.
	 * @param CartData      $cart_data       The cart data.
	 * @param PayPalCart    $cart            The PayPal cart with customer data.
	 * @param PaymentMethod $payment_method  The payment method data.
	 * @param string        $paypal_order_id The PayPal order ID.
	 * @return WC_Order|WP_Error The created order or error.
	 */
	private function create_order( PayPalOrder $paypal_order, $cart_data, PayPalCart $cart, PaymentMethod $payment_method, string $paypal_order_id ) {
		// Build PayPal-specific data for order creation.
		$paypal_data = array(
			'payment_method' => array(
				'token'    => $payment_method->token(),
				'payer_id' => $payment_method->payer_id(),
			),
		);

		// Add payer information.
		$payer_data = $this->build_payer_data( $cart );
		if ( ! empty( $payer_data ) ) {
			$paypal_data['payer'] = $payer_data;
		}

		$shipping_data = $this->build_shipping_data( $cart );
		if ( ! empty( $shipping_data ) ) {
			$paypal_data['shipping_address'] = $shipping_data;
		}

		// Create WooCommerce order.
		$wc_order = $this->wc_order_creator->create_from_paypal_order(
			$paypal_order,
			$cart_data,
			$paypal_data
		);

		// Mark as agentic commerce order with metadata.
		$wc_order->update_meta_data( '_paypal_order_id', $paypal_order_id );
		$wc_order->update_meta_data( '_agentic_commerce', '1' );
		$wc_order->set_status( 'on-hold', 'Awaiting PayPal payment capture.' );
		$wc_order->save();

		return $wc_order;
	}

	/**
	 * Build payer data from PayPal cart.
	 *
	 * @param PayPalCart $cart The PayPal cart.
	 * @return array Payer data array.
	 */
	private function build_payer_data( PayPalCart $cart ): array {
		if ( ! $cart->customer() && ! $cart->billing_address() ) {
			return array();
		}

		$payer_data = array();

		$customer = $cart->customer();
		if ( $customer ) {
			// Note: name is an array with items 'given_name' and 'surname', which can be string or null.
			$payer_data['name'] = $customer->name();

			if ( $customer->email_address() ) {
				$payer_data['email_address'] = $customer->email_address();
			}
		}

		if ( $cart->billing_address() ) {
			$payer_data['address'] = CartHelper::billing_address_array( $cart );
		}

		return $payer_data;
	}

	/**
	 * Build shipping data from PayPalCart.
	 *
	 * @param PayPalCart $cart The PayPal cart.
	 * @return array Shipping data array.
	 */
	private function build_shipping_data( PayPalCart $cart ): array {
		if ( ! $cart->shipping_address() ) {
			return array();
		}

		return array(
			'name'    => array(
				'full_name' => CartHelper::full_customer_name( $cart ),
			),
			'address' => CartHelper::shipping_address_array( $cart ),
		);
	}

	/**
	 * Link PayPal order with WooCommerce order ID.
	 *
	 * Delegates to PayPalOrderManager for the PATCH operation.
	 *
	 * @param string   $paypal_order_id The PayPal order ID.
	 * @param WC_Order $wc_order        The WooCommerce order.
	 * @return void
	 */
	private function link_orders( string $paypal_order_id, WC_Order $wc_order ): void {
		$this->order_manager->link_wc_order( $paypal_order_id, $wc_order->get_id() );
	}

	/**
	 * Capture PayPal payment and update WC order.
	 *
	 * Delegates to PayPalOrderManager for the capture operation.
	 *
	 * @param PayPalOrder $paypal_order    The PayPal order object (unused, kept for signature
	 *                                     compatibility).
	 * @param WC_Order    $wc_order        The WooCommerce order.
	 * @param string      $paypal_order_id The PayPal order ID.
	 * @return void
	 */
	private function capture_payment( PayPalOrder $paypal_order, WC_Order $wc_order, string $paypal_order_id ): void {
		$capture_result = $this->order_manager->capture_order( $paypal_order_id );

		if ( $capture_result ) {
			$wc_order->payment_complete( $paypal_order_id );

			$transaction_id = $capture_result['transaction_id'] ?? $paypal_order_id;
			$wc_order->add_order_note(
				sprintf(
				/* translators: %s: PayPal transaction ID */
					__( 'PayPal payment captured. Transaction ID: %s', 'woocommerce-paypal-payments' ),
					$transaction_id
				)
			);
			$wc_order->save();
		}
		// If capture_result is null, payment can be handled manually or via webhook.
	}
}
