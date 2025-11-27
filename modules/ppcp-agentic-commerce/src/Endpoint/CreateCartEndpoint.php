<?php
/**
 * Create Cart Endpoint for Agentic Commerce.
 *
 * POST /api/paypal/v1/merchant-cart
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WP_REST_Request;
use WP_REST_Response;
use WC_Product;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Address;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AmountBreakdown;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\BadRequestError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Cart\PayPalCartToCartDataAdapter;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\AuthServiceProvider;

/**
 * Create Cart REST endpoint.
 */
class CreateCartEndpoint extends AgenticRestEndpoint {

	/**
	 * The endpoint path following PayPal specs.
	 */
	private const PATH = 'merchant-cart';

	/**
	 * The expected HTTP method.
	 */
	private const METHOD = 'POST';

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::PATH,
			array(
				'methods'             => self::METHOD,
				'callback'            => array( $this, 'create_cart' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Create a new cart.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The REST response.
	 */
	public function create_cart( WP_REST_Request $request ): WP_REST_Response {
		$cart = $this->parse_and_validate_cart( $request );

		if ( $cart instanceof AgenticError ) {
			return $this->error( $cart );
		}

		// Create PayPal Order via Orders API.
		try {
			$ec_token = $this->create_paypal_order( $cart );
		} catch ( \Exception $e ) {
			return $this->error(
				new BadRequestError(
					'Failed to create PayPal Order: ' . $e->getMessage()
				)
			);
		}

		$cart_id = $this->session_handler->create_cart_session( $cart, $ec_token );

		$response = $this->response_factory->new_cart( $cart, $cart_id, $ec_token );

		return $this->cart_details( $response, 201 );
	}

	/**
	 * Create PayPal Order from cart WITHOUT creating a WooCommerce order.
	 *
	 * This follows the agentic commerce pattern where:
	 * 1. CreateCart: Creates PayPal order + stores cart in session (NO WC order)
	 * 2. Checkout: Creates WC order + captures payment
	 *
	 * @param PayPalCart $cart The cart.
	 * @return string The PayPal Order ID (ec_token).
	 * @throws \Exception If order creation fails.
	 */
	protected function create_paypal_order( PayPalCart $cart ): string {
		// Step 1: Translate PayPalCart to CartData for validation.
		$cart_data = $this->cart_translator->translate( $cart );

		// Step 2: Build a minimal PurchaseUnit directly from cart.
		// We can't use from_wc_order() yet because there's no WC order.
		$purchase_unit = $this->build_purchase_unit_from_cart( $cart, $cart_data );

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
	 * Build a PurchaseUnit from cart without a WC order.
	 *
	 * This creates a minimal purchase unit for PayPal order creation.
	 * The full purchase unit with proper amounts will be created later
	 * when the WC order is created during checkout.
	 *
	 * @param PayPalCart $cart      The PayPal cart.
	 * @param CartData   $cart_data The translated cart data.
	 * @return PurchaseUnit
	 */
	private function build_purchase_unit_from_cart(
		PayPalCart $cart,
		CartData $cart_data
	): PurchaseUnit {
		// Calculate total from cart items.
		$total = 0.0;
		foreach ( $cart_data->items() as $item ) {
			$total += (float) $item['line_total'];
		}

		// Get currency from first item or default to USD.
		$currency = 'USD';
		if ( ! empty( $cart_data->items() ) ) {
			$first_item = reset( $cart_data->items() );
			$product    = $first_item['data'] ?? null;
			if ( $product instanceof \WC_Product ) {
				$currency = get_woocommerce_currency();
			}
		}

		// Build items for the purchase unit.
		$items = array();
		foreach ( $cart_data->items() as $cart_item ) {
			$product = $cart_item['data'] ?? null;
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$item = new Item(
				substr( $product->get_name(), 0, 127 ),
				new Money(
					(float) $product->get_price(),
					$currency
				),
				(int) $cart_item['quantity'],
				substr( $product->get_description(), 0, 127 ),
				null, // tax.
				substr( $product->get_sku(), 0, 127 ),
				$product->is_virtual() ?
					Item::DIGITAL_GOODS :
					Item::PHYSICAL_GOODS
			);

			$items[] = $item;
		}

		// Build amount breakdown (required when items are present).
		$breakdown = new AmountBreakdown(
			new Money( $total, $currency ),
			null, // shipping.
			null, // tax_total.
			null, // insurance.
			null, // handling.
			null, // shipping_discount.
			null  // discount.
		);

		// Build amount with breakdown.
		$amount = new Amount(
			new Money( $total, $currency ),
			$breakdown
		);

		// Build shipping if needed.
		$shipping = null;
		if ( $cart->shipping_address() && $cart->customer() ) {
			$shipping = $this->build_shipping_from_cart( $cart );
		}

		return new PurchaseUnit(
			$amount,
			$items,
			$shipping,
			'default', // reference_id.
			'',        // description.
			'',        // custom_id - will be set during checkout when WC order is created.
			'',        // invoice_id - will be set during checkout.
			'',        // soft_descriptor.
			null // payee.
		);
	}

	/**
	 * Build shipping entity from cart.
	 *
	 * @param PayPalCart $cart The cart.
	 * @return Shipping|null
	 */
	private function build_shipping_from_cart( PayPalCart $cart ): ?Shipping {
		$customer = $cart->customer();
		$shipping = $cart->shipping_address();

		if ( ! $customer || ! $shipping ) {
			return null;
		}

		$address = new Address(
			$shipping->country_code() ?? '',
			$shipping->address_line_1() ?? '',
			$shipping->address_line_2(),
			$shipping->admin_area_2() ?? '', // city.
			$shipping->admin_area_1() ?? '', // state.
			$shipping->postal_code() ?? ''
		);

		// Use customer's name for shipping recipient.
		$full_name = '';
		if ( $customer->name() ) {
			$name      = $customer->name();
			$full_name = trim( ( $name['given_name'] ?? '' ) . ' ' . ( $name['surname'] ?? '' ) );
		}

		return new Shipping(
			$full_name,
			$address,
			null,    // email_address.
			null,    // phone_number.
			array()  // options.
		);
	}
}
