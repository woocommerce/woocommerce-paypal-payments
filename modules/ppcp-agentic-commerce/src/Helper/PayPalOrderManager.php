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

use RuntimeException;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

/**
 * Manages PayPal Order creation and updates.
 */
class PayPalOrderManager {
	private OrderEndpoint $order_endpoint;

	private Orders $orders_api;

	private PayPalOrderBuilder $order_builder;

	private CartTransformer $cart_transformer;

	private LoggerInterface $logger;

	public function __construct(
		OrderEndpoint $order_endpoint,
		Orders $orders_api,
		PayPalOrderBuilder $order_builder,
		CartTransformer $cart_transformer,
		LoggerInterface $logger
	) {

		$this->order_endpoint   = $order_endpoint;
		$this->orders_api       = $orders_api;
		$this->order_builder    = $order_builder;
		$this->cart_transformer = $cart_transformer;
		$this->logger           = $logger;
	}

	/**
	 * Create a new PayPal Order from cart WITHOUT creating a WooCommerce order.
	 *
	 * This follows the agentic commerce pattern where:
	 * 1. CreateCart: Creates PayPal order + stores cart in session (NO WC order)
	 * 2. Checkout: Creates WC order + captures payment
	 *
	 * @param PayPalCart $cart The cart.
	 * @return string The PayPal Order ID (ec_token) or an empty string.
	 */
	public function create_order( PayPalCart $cart ): string {
		$this->logger->info(
			'[ORDER] Creating PayPal Order',
			array(
				'item_count' => count( $cart->items() ),
				'cart'       => $cart->to_array(),
			)
		);

		try {
			// Step 1: Transform PayPalCart to CartData.
			$cart_data = $this->cart_transformer->paypal_cart_to_wc_cart( $cart );

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

			$order_id = $paypal_order->id();

			$this->logger->info(
				'[ORDER] PayPal Order created successfully',
				array(
					'order_id'   => $order_id,
					'item_count' => count( $cart->items() ),
				)
			);

			return $order_id;
		} catch ( RuntimeException $error ) {
			$this->logger->error(
				'[ORDER] PayPal Order creation failed',
				array(
					'error'      => $error->getMessage(),
					'item_count' => count( $cart->items() ),
				)
			);
		}

		return '';
	}

	/**
	 * Update an existing PayPal Order with new cart data via PATCH API.
	 *
	 * @param string     $order_id The PayPal Order ID.
	 * @param PayPalCart $cart     The updated cart.
	 * @throws RuntimeException If the update fails.
	 */
	public function update_order( string $order_id, PayPalCart $cart ): void {
		$totals = $this->calculate_cart_totals( $cart );

		$this->logger->info(
			'[ORDER] Updating PayPal Order',
			array(
				'order_id' => $order_id,
				'totals'   => $totals,
			)
		);

		// TODO - patch order does not update the cart items??
		$cart_amount = $totals['amount'];
		$patch_data  = array(
			array(
				'op'    => 'replace',
				'path'  => "/purchase_units/@reference_id=='default'/amount",
				'value' => array(
					'currency_code' => $cart_amount['currency_code'],
					'value'         => $cart_amount['value'],
					'breakdown'     => array(
						'item_total' => $totals['item_total'],
						'shipping'   => $totals['shipping'],
						'tax_total'  => $totals['tax_total'],
					),
				),
			),
		);

		try {
			$this->orders_api->patch_order( $order_id, $patch_data );

			$this->logger->info(
				'[ORDER] PayPal Order updated successfully',
				array(
					'order_id' => $order_id,
					'amount'   => $cart_amount['value'],
				)
			);
		} catch ( RuntimeException $error ) {
			$this->logger->error(
				'[ORDER] PayPal Order update failed',
				array(
					'order_id' => $order_id,
					'error'    => $error->getMessage(),
					'totals'   => $totals,
				)
			);

			throw $error;
		}
	}

	/**
	 * Fetch a PayPal Order by ID.
	 *
	 * @param string $order_id The PayPal Order ID.
	 * @return \WooCommerce\PayPalCommerce\ApiClient\Entity\Order The PayPal Order.
	 * @throws RuntimeException If fetching fails.
	 */
	public function fetch_order( string $order_id ) {
		$this->logger->info(
			'[ORDER] Fetching PayPal Order',
			array( 'order_id' => $order_id )
		);

		try {
			$paypal_order = $this->order_endpoint->order( $order_id );

			$this->logger->info(
				'[ORDER] PayPal Order fetched successfully',
				array(
					'order_id' => $order_id,
					'status'   => $paypal_order->status(),
				)
			);

			return $paypal_order;

		} catch ( RuntimeException $error ) {
			$this->logger->error(
				'[ORDER] Failed to fetch PayPal Order',
				array(
					'order_id' => $order_id,
					'error'    => $error->getMessage(),
				)
			);

			throw $error;
		}
	}

	/**
	 * Link PayPal Order with WooCommerce order ID.
	 *
	 * Updates the PayPal order's custom_id field with the WC order ID
	 * to enable webhook matching and order correlation.
	 *
	 * @param string $order_id    The PayPal Order ID.
	 * @param int    $wc_order_id The WooCommerce order ID.
	 * @return void
	 */
	public function link_wc_order( string $order_id, int $wc_order_id ): void {
		$this->logger->info(
			'[ORDER] Linking WooCommerce order to PayPal Order',
			array(
				'order_id'    => $order_id,
				'wc_order_id' => $wc_order_id,
			)
		);

		$patch_data = array(
			array(
				'op'    => 'add',
				'path'  => '/purchase_units/@reference_id==\'default\'/custom_id',
				'value' => (string) $wc_order_id,
			),
		);

		try {
			$this->orders_api->patch_order( $order_id, $patch_data );

			$this->logger->info(
				'[ORDER] WooCommerce order linked successfully',
				array(
					'order_id'    => $order_id,
					'wc_order_id' => $wc_order_id,
				)
			);

		} catch ( RuntimeException $error ) {
			$this->logger->warning(
				'[ORDER] Failed to link WooCommerce order',
				array(
					'order_id'    => $order_id,
					'wc_order_id' => $wc_order_id,
					'error'       => $error->getMessage(),
				)
			);

			// Don't throw: Order was created, webhook matching can still work via _paypal_order_id meta.
		}
	}

	/**
	 * Capture PayPal Order payment.
	 *
	 * Captures the authorized payment for the order.
	 *
	 * @param string $order_id The PayPal Order ID.
	 * @return array|null Capture the result with transaction_id, or null on failure.
	 */
	public function capture_order( string $order_id ): ?array {
		$this->logger->info(
			'[ORDER] Capturing PayPal Order payment',
			array( 'order_id' => $order_id )
		);

		try {
			$paypal_order = $this->fetch_order( $order_id );

			$capture_result = $this->order_endpoint->capture( $paypal_order );

			$transaction_id = $order_id;
			$payments       = $capture_result->purchase_units()[0]->payments();

			if ( $payments ) {
				$transaction_id = $payments->captures()[0]->id();
			}

			$this->logger->info(
				'[ORDER] PayPal Order payment captured successfully',
				array(
					'order_id'       => $order_id,
					'transaction_id' => $transaction_id,
				)
			);

			return array(
				'order_id'       => $order_id,
				'transaction_id' => $transaction_id,
			);

		} catch ( RuntimeException $error ) {
			$this->logger->error(
				'[ORDER] PayPal Order capture failed',
				array(
					'order_id' => $order_id,
					'error'    => $error->getMessage(),
				)
			);

			// Return null - payment can be handled manually or via webhook.
			return null;
		}
	}

	/**
	 * Calculate cart totals from items.
	 *
	 * @param PayPalCart $cart The cart.
	 * @return array The totals array with currency_code and value for each total.
	 */
	private function calculate_cart_totals( PayPalCart $cart ): array {
		$currency_code = CartHelper::currency( $cart );
		$item_total    = CartHelper::cart_item_total( $cart );

		return array(
			'item_total' => array(
				'currency_code' => $currency_code,
				'value'         => $item_total,
			),
			'shipping'   => array(
				'currency_code' => $currency_code,
				'value'         => 0.00,
			),
			'tax_total'  => array(
				'currency_code' => $currency_code,
				'value'         => 0.00,
			),
			'amount'     => array(
				'currency_code' => $currency_code,
				'value'         => $item_total,
			),
		);
	}
}
