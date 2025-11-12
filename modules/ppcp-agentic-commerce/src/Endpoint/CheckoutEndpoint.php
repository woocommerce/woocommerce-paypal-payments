<?php
/**
 * Checkout Endpoint for Agentic Commerce.
 *
 * POST /api/paypal/v1/merchant-cart/{cartId}/checkout
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\InternalServerError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\NotFoundError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PaymentMethod;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InsufficientQuantity;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ItemOutOfStock;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;
use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

/**
 * Checkout REST endpoint.
 */
class CheckoutEndpoint extends AgenticRestEndpoint {
	/**
	 * The endpoint path following PayPal specs.
	 */
	protected const PATH = 'merchant-cart/(?P<cart_id>[a-zA-Z0-9_-]+)/checkout';

	/**
	 * The expected HTTP method.
	 */
	protected const METHOD = 'POST';

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
				'callback'            => array( $this, 'complete_checkout' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'cart_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return ! empty( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Complete the checkout process.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The REST response.
	 */
	public function complete_checkout( WP_REST_Request $request ): WP_REST_Response {
		$cart_id = $request->get_param( 'cart_id' );
		$data    = $this->parse_json_body( $request );

		if ( $data instanceof AgenticError ) {
			return $this->error( $data );
		}

		$payment_method        = PaymentMethod::from_array( $data['payment_method'] );
		$payment_method_issues = $payment_method->validate();

		if ( ! empty( $payment_method_issues ) ) {
			return $this->error(
				new InternalServerError(
					'Payment method is required for checkout',
					$payment_method_issues
				)
			);
		}

		// Load the cart session.
		$cart_session = $this->session_handler->load_cart_session( $cart_id );
		if ( ! $cart_session ) {
			return $this->error( new NotFoundError( 'Cart not found: ' . $cart_id ) );
		}

		// Parse the incoming cart data.
		try {
			$cart = PayPalCart::from_array( $data );
		} catch ( \Exception $e ) {
			return $this->error(
				new InternalServerError( 'Invalid cart data: ' . $e->getMessage() )
			);
		}

		// Validate products exist in WooCommerce before proceeding.
		$validation_issues = $this->validate_products_exist( $cart );
		$validation_issues = array_merge( $validation_issues, $this->verify_inventory( $cart ) );
		if ( ! empty( $validation_issues ) ) {
			$cart = $cart->with_validation_issues( ...$validation_issues );
			return $this->cart_details( $this->response_factory->active_cart( $cart, $cart_id, $cart_session['ec_token'] ) );
		}

		try {
			// Create WooCommerce order.
			$order = $this->create_wc_order( $cart, $payment_method );
			if ( is_wp_error( $order ) ) {
				return $this->error(
					InternalServerError::from_wp_error( $order )
				);
			}

			// Remove session.
			$this->session_handler->destroy_cart_session( $cart_id );

			// Build the response with payment confirmation.
			$response = $this->response_factory->from_order(
				$order,
				$cart
			);

			return $this->cart_details( $response );

		} catch ( \Exception $e ) {
			return $this->error(
				new InternalServerError(
					'A temporary system error occurred. Please try again later.'
				)
			);
		}
	}

	/**
	 * Verify inventory availability using WooCommerce stock management.
	 *
	 * @param PayPalCart $cart The cart to verify.
	 * @return ValidationIssue[] Array of validation issues if any.
	 */
	private function verify_inventory( PayPalCart $cart ): array {
		$issues = array();

		foreach ( $cart->items() as $item ) {
			// Get WooCommerce product.
			$product_id = wc_get_product_id_by_sku( $item->variant_id() );
			if ( ! $product_id ) {
				$product_id = wc_get_product_id_by_sku( $item->item_id() );
			}

			if ( ! $product_id ) {
				continue; // Skip if product not found.
			}

			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			// Check stock status.
			if ( ! $product->is_in_stock() ) {
				$issues[] = new ItemOutOfStock(
					'Product is no longer available',
					sprintf( '%s is currently out of stock.', $product->get_name() ),
				);
			}

			// Check quantity if managing stock.
			if ( $product->managing_stock() ) {
				$stock_quantity = $product->get_stock_quantity();
				if ( $stock_quantity < $item->quantity() ) {
					$issues[] = new InsufficientQuantity(
						'Insufficient inventory',
						// TODO should we actually expose the real stock qty here?
						sprintf(
							'Only %d of %s available, but %d requested.',
							$stock_quantity,
							$product->get_name(),
							$item->quantity()
						),
					);
				}
			}
		}

		return $issues;
	}

	/**
	 * Create a WooCommerce order from the cart data.
	 *
	 * @param PayPalCart    $cart           The cart data.
	 * @param PaymentMethod $payment_method The payment method data.
	 * @return \WC_Order|\WP_Error The created order or error.
	 */
	private function create_wc_order( PayPalCart $cart, PaymentMethod $payment_method ) {
		// TODO This is placeholder code. We need a translation from PayPalCart -> WC_Cart -> WC_Order here
		try {
			$order = wc_create_order();

			// Add items to order.
			foreach ( $cart->items() as $item ) {
				$product_id = wc_get_product_id_by_sku( $item->variant_id() );
				if ( ! $product_id ) {
					$product_id = wc_get_product_id_by_sku( $item->item_id() );
				}

				if ( $product_id ) {
					$product = wc_get_product( $product_id );
					if ( $product ) {
						$order->add_product( $product, $item->quantity() );
					}
				}
			}

			// Set customer information.
			$customer = $cart->customer();
			if ( $customer ) {
				if ( $customer->email_address() ) {
					$order->set_billing_email( $customer->email_address() );
				}

				$name = $customer->name();
				if ( $name ) {
					$order->set_billing_first_name( $name['given_name'] ?? '' );
					$order->set_billing_last_name( $name['surname'] ?? '' );
				}

				$phone = $customer->phone();
				if ( $phone ) {
					$order->set_billing_phone(
						'+' . $phone['country_code'] . $phone['national_number']
					);
				}
			}

			// Set addresses.
			$shipping_address = $cart->shipping_address();
			if ( $shipping_address ) {
				$order->set_shipping_address_1( $shipping_address->get_address_line_1() );
				$order->set_shipping_address_2( $shipping_address->get_address_line_2() ?? '' );
				$order->set_shipping_city( $shipping_address->get_admin_area_2() );
				$order->set_shipping_state( $shipping_address->get_admin_area_1() );
				$order->set_shipping_postcode( $shipping_address->get_postal_code() );
				$order->set_shipping_country( $shipping_address->get_country_code() );
			}

			$billing_address = $cart->billing_address();
			if ( $billing_address ) {
				$order->set_billing_address_1( $billing_address->get_address_line_1() );
				$order->set_billing_address_2( $billing_address->get_address_line_2() ?? '' );
				$order->set_billing_city( $billing_address->get_admin_area_2() );
				$order->set_billing_state( $billing_address->get_admin_area_1() );
				$order->set_billing_postcode( $billing_address->get_postal_code() );
				$order->set_billing_country( $billing_address->get_country_code() );
			}

			// Set payment method.
			$order->set_payment_method( 'ppcp-gateway' );
			$order->set_payment_method_title( 'PayPal' );

			// Store PayPal token for processing.
			$order->update_meta_data( '_paypal_token', $payment_method->token() );
			$order->update_meta_data( '_paypal_payer_id', $payment_method->payer_id() );

			// Calculate totals.
			$order->calculate_totals();

			// Save the order.
			$order->save();

			return $order;

		} catch ( \Exception $e ) {
			return new \WP_Error( 'order_creation_failed', $e->getMessage() );
		}
	}
}
