<?php
/**
 * PayPal Order Manager for Agentic Commerce.
 *
 * Unified interface for PayPal Order lifecycle management (create, update).
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;

use WooCommerce\PayPalCommerce\AgenticCommerce\Cart\PayPalCartToCartDataAdapter;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use Exception;

/**
 * Manages PayPal Order creation and updates.
 */
class PayPalOrderManager {
	private OrderEndpoint $order_endpoint;

	private Orders $orders_api;

	private PayPalOrderBuilder $order_builder;

	private PayPalCartToCartDataAdapter $cart_translator;

	public function __construct(
		OrderEndpoint $order_endpoint,
		Orders $orders_api,
		PayPalOrderBuilder $order_builder,
		PayPalCartToCartDataAdapter $cart_translator
	) {

		$this->order_endpoint  = $order_endpoint;
		$this->orders_api      = $orders_api;
		$this->order_builder   = $order_builder;
		$this->cart_translator = $cart_translator;
	}

	/**
	 * Create a new PayPal Order from cart WITHOUT creating a WooCommerce order.
	 *
	 * This follows the agentic commerce pattern where:
	 * 1. CreateCart: Creates PayPal order + stores cart in session (NO WC order)
	 * 2. Checkout: Creates WC order + captures payment
	 *
	 * @param PayPalCart $cart The cart.
	 * @return string The PayPal Order ID (ec_token).
	 * @throws Exception If order creation fails.
	 */
	public function create_order( PayPalCart $cart ): string {
		// Step 1: Translate PayPalCart to CartData for validation.
		$cart_data = $this->cart_translator->translate( $cart );

		// Step 2: Build a minimal PurchaseUnit directly from cart.
		// We can't use from_wc_order() yet because there's no WC order.
		$purchase_unit = $this->order_builder->build_purchase_unit_from_cart( $cart, $cart_data );

		// Step 3: Create PayPal Order (application_context filter is registered in AgenticCommerceModule).
		$paypal_order = $this->order_endpoint->create(
			array( $purchase_unit ),
			ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING,
			null,               // payer.
			'agentic-commerce', // payment_method identifier.
			array(),            // request_data.
			null                // payment_source.
		);

		return $paypal_order->id();
	}

	/**
	 * Update an existing PayPal Order with new cart data via PATCH API.
	 *
	 * @param string     $order_id The PayPal Order ID.
	 * @param PayPalCart $cart     The updated cart.
	 * @throws Exception If PATCH fails.
	 */
	public function update_order( string $order_id, PayPalCart $cart ): void {
		// Calculate totals from cart items.
		$totals = $this->calculate_cart_totals( $cart );

		$patch_data = array(
			array(
				'op'    => 'replace',
				'path'  => "/purchase_units/@reference_id=='default'/amount",
				'value' => array(
					'currency_code' => $totals['amount']['currency_code'],
					'value'         => $totals['amount']['value'],
					'breakdown'     => array(
						'item_total' => array(
							'currency_code' => $totals['item_total']['currency_code'],
							'value'         => $totals['item_total']['value'],
						),
						'shipping'   => array(
							'currency_code' => $totals['shipping']['currency_code'],
							'value'         => $totals['shipping']['value'],
						),
						'tax_total'  => array(
							'currency_code' => $totals['tax_total']['currency_code'],
							'value'         => $totals['tax_total']['value'],
						),
					),
				),
			),
		);

		$this->orders_api->patch_order( $order_id, $patch_data );
	}

	/**
	 * Calculate cart totals from items.
	 *
	 * @param PayPalCart $cart The cart.
	 * @return array The totals array with currency_code and value for each total.
	 */
	private function calculate_cart_totals( PayPalCart $cart ): array {
		$cart_array = $cart->to_array();

		$currency_code = $cart_array['items'][0]['price']['currency_code'] ?? 'USD';

		$item_total = array_reduce(
			$cart_array['items'] ?? array(),
			function ( float $sum, $item ): float {
				return $sum + ( (float) $item['price']['value'] * $item['quantity'] );
			},
			0.0
		);

		// Format as string with 2 decimal places.
		$item_total_str = number_format( $item_total, 2, '.', '' );

		return array(
			'item_total' => array(
				'currency_code' => $currency_code,
				'value'         => $item_total_str,
			),
			'shipping'   => array(
				'currency_code' => $currency_code,
				'value'         => '0.00',
			),
			'tax_total'  => array(
				'currency_code' => $currency_code,
				'value'         => '0.00',
			),
			'amount'     => array(
				'currency_code' => $currency_code,
				'value'         => $item_total_str,
			),
		);
	}
}
