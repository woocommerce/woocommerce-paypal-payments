<?php
/**
 * Agentic Checkout Processor.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Helper;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PaymentMethod;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Address;
use WooCommerce\PayPalCommerce\AgenticCommerce\Cart\PayPalCartToCartDataAdapter;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order as PayPalOrder;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WooCommerce\PayPalCommerce\Button\Exception\ValidationException;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;
use WC_Order;
use WP_Error;

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

	/**
	 * The PayPal Orders API endpoint (high-level).
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * The PayPal Orders API endpoint (low-level).
	 *
	 * @var Orders
	 */
	private $orders_api;

	/**
	 * The WooCommerce order creator.
	 *
	 * @var WooCommerceOrderCreator
	 */
	private $wc_order_creator;

	/**
	 * The cart translator.
	 *
	 * @var PayPalCartToCartDataAdapter
	 */
	private $cart_translator;

	/**
	 * Constructor.
	 *
	 * @param OrderEndpoint               $order_endpoint PayPal Orders API endpoint (high-level).
	 * @param Orders                      $orders_api PayPal Orders API endpoint (low-level).
	 * @param WooCommerceOrderCreator     $wc_order_creator WooCommerce order creator.
	 * @param PayPalCartToCartDataAdapter $cart_translator Cart translator.
	 */
	public function __construct(
		OrderEndpoint $order_endpoint,
		Orders $orders_api,
		WooCommerceOrderCreator $wc_order_creator,
		PayPalCartToCartDataAdapter $cart_translator
	) {
		$this->order_endpoint   = $order_endpoint;
		$this->orders_api       = $orders_api;
		$this->wc_order_creator = $wc_order_creator;
		$this->cart_translator  = $cart_translator;
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
	 * @param PayPalCart    $cart The PayPal cart data.
	 * @param PaymentMethod $payment_method The payment method data.
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
			$paypal_order = $this->order_endpoint->order( $paypal_order_id );

			// Step 2: Translate PayPalCart to CartData.
			try {
				$cart_data = $this->cart_translator->translate( $cart );
			} catch ( ValidationException $e ) {
				return new WP_Error(
					'cart_validation_failed',
					'Cart validation failed: ' . $e->getMessage(),
					array( 'errors' => $e->errors() )
				);
			}

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
	 * @param PayPalOrder   $paypal_order The PayPal order object.
	 * @param CartData      $cart_data The cart data.
	 * @param PayPalCart    $cart The PayPal cart with customer data.
	 * @param PaymentMethod $payment_method The payment method data.
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

		// Add shipping address.
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
		$wc_order->update_meta_data( '_agentic_commerce', true );
		$wc_order->set_status( 'on-hold', 'Awaiting PayPal payment capture.' );
		$wc_order->save();

		return $wc_order;
	}

	/**
	 * Build payer data from PayPal cart.
	 *
	 * @param PayPalCart $cart The PayPal cart with customer data.
	 * @return array Payer data array.
	 */
	private function build_payer_data( PayPalCart $cart ): array {
		if ( ! $cart->customer() && ! $cart->billing_address() ) {
			return array();
		}

		$payer_data = array();

		// Add email address.
		if ( $cart->customer() && $cart->customer()->email_address() ) {
			$payer_data['email_address'] = $cart->customer()->email_address();
		}

		// Add billing address.
		if ( $cart->billing_address() ) {
			/** @var Address $billing */
			$billing               = $cart->billing_address();
			$payer_data['name']    = array(
				'given_name' => $billing->given_name() ?? '',
				'surname'    => $billing->surname() ?? '',
			);
			$payer_data['address'] = array(
				'address_line_1' => $billing->address_line_1() ?? '',
				'address_line_2' => $billing->address_line_2() ?? '',
				'admin_area_2'   => $billing->admin_area_2() ?? '',
				'admin_area_1'   => $billing->admin_area_1() ?? '',
				'postal_code'    => $billing->postal_code() ?? '',
				'country_code'   => $billing->country_code() ?? '',
			);
		}

		return $payer_data;
	}

	/**
	 * Build shipping data from PayPal cart.
	 *
	 * @param PayPalCart $cart The PayPal cart with shipping address.
	 * @return array Shipping data array.
	 */
	private function build_shipping_data( PayPalCart $cart ): array {
		if ( ! $cart->shipping_address() ) {
			return array();
		}

		/** @var Address $shipping */
		$shipping = $cart->shipping_address();

		return array(
			'name'    => array(
				'full_name' => trim(
					( $shipping->given_name() ?? '' ) . ' ' . ( $shipping->surname() ?? '' )
				),
			),
			'address' => array(
				'address_line_1' => $shipping->address_line_1() ?? '',
				'address_line_2' => $shipping->address_line_2() ?? '',
				'admin_area_2'   => $shipping->admin_area_2() ?? '',
				'admin_area_1'   => $shipping->admin_area_1() ?? '',
				'postal_code'    => $shipping->postal_code() ?? '',
				'country_code'   => $shipping->country_code() ?? '',
			),
		);
	}

	/**
	 * Link PayPal order with WooCommerce order ID.
	 *
	 * Updates the PayPal order's custom_id field with the WC order ID
	 * to enable webhook matching.
	 *
	 * @param string   $paypal_order_id The PayPal order ID.
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @return void
	 */
	private function link_orders( string $paypal_order_id, WC_Order $wc_order ): void {
		try {
			$patch_data = array(
				array(
					'op'    => 'add',
					'path'  => '/purchase_units/@reference_id==\'default\'/custom_id',
					'value' => (string) $wc_order->get_id(),
				),
			);
			$this->orders_api->patch_order( $paypal_order_id, $patch_data );
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Intentionally empty - manual processing still possible.
			// The order is created, webhook matching can still work via _paypal_order_id meta.
		}
	}

	/**
	 * Capture PayPal payment and update WC order.
	 *
	 * @param PayPalOrder $paypal_order The PayPal order object.
	 * @param WC_Order    $wc_order The WooCommerce order.
	 * @param string      $paypal_order_id The PayPal order ID.
	 * @return void
	 */
	private function capture_payment( PayPalOrder $paypal_order, WC_Order $wc_order, string $paypal_order_id ): void {
		try {
			$capture_result = $this->order_endpoint->capture( $paypal_order );
			$wc_order->payment_complete( $paypal_order_id );

			$transaction_id = $capture_result->purchase_units()[0]->payments()->captures()[0]->id() ?? $paypal_order_id;
			$wc_order->add_order_note(
				sprintf(
				/* translators: %s: PayPal transaction ID */
					__( 'PayPal payment captured. Transaction ID: %s', 'woocommerce-paypal-payments' ),
					$transaction_id
				)
			);
			$wc_order->save();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Intentionally empty - payment can be handled manually or via webhook.
		}
	}
}
