<?php
/**
 * Replace Cart Endpoint for Agentic Commerce.
 *
 * PUT /api/paypal/v1/merchant-cart/{cart_id}
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WP_REST_Request;
use WP_REST_Response;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\NotFoundError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;

/**
 * Replace Cart REST endpoint.
 *
 * Fully replaces an existing cart while preserving the payment token.
 */
class ReplaceCartEndpoint extends AgenticRestEndpoint {
	/**
	 * The endpoint path following PayPal specs.
	 */
	private const PATH = 'merchant-cart/(?P<cart_id>[a-zA-Z0-9_-]+)';

	/**
	 * The expected HTTP method.
	 */
	private const METHOD = 'PUT';

	/**
	 * The PayPal Orders API endpoint (low-level).
	 *
	 * @var Orders
	 */
	protected $orders_api;

	public function __construct(
		AuthServiceProvider $auth_provider,
		AgenticSessionHandler $session_handler,
		ResponseFactory $response_factory,
		LoggerInterface $logger,
		Orders $orders_api
	) {

		parent::__construct( $auth_provider, $session_handler, $response_factory, $logger );
		$this->orders_api = $orders_api;
	}

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
				'callback'            => array( $this, 'replace_cart' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'cart_id' => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && strlen( $param ) >= 10;
						},
					),
				),
			)
		);
	}

	/**
	 * Replace an existing cart with new data.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The REST response.
	 */
	public function replace_cart( WP_REST_Request $request ): WP_REST_Response {
		$cart_id = $request->get_param( 'cart_id' );

		// Verify cart exists.
		$session = $this->load_cart_session( $cart_id );

		if ( $session instanceof AgenticError ) {
			return $this->error( $session );
		}

		$new_cart = $this->parse_and_validate_cart( $request );

		if ( $new_cart instanceof AgenticError ) {
			return $this->error( $new_cart );
		}

		// Get the PayPal Order ID (ec_token).
		$paypal_order_id = $session['ec_token'];

		// PATCH the PayPal Order with new totals.
		try {
			$this->patch_paypal_order( $paypal_order_id, $new_cart );
		} catch ( \Exception $e ) {
			return $this->error(
				new NotFoundError(
					'Failed to update PayPal Order: ' . $e->getMessage(),
					array(
						array(
							'issue'       => 'PAYPAL_ORDER_UPDATE_FAILED',
							'description' => 'Could not synchronize cart changes with PayPal.',
						),
					)
				)
			);
		}

		// Replace the cart session (preserving ec_token).
		$update_result = $this->session_handler->update_cart_session( $cart_id, $new_cart );

		if ( ! $update_result ) {
			return $this->error(
				new NotFoundError(
					'Failed to replace cart',
					array(
						array(
							'issue'       => 'CART_REPLACE_FAILED',
							'description' => 'Cart replacement operation failed.',
						),
					)
				)
			);
		}

		$response = $this->response_factory->from_cart( $new_cart );

		return $this->cart_details( $response, 200 );
	}

	/**
	 * PATCH PayPal Order with updated totals.
	 *
	 * @param string     $order_id The PayPal Order ID.
	 * @param PayPalCart $cart The updated cart.
	 * @throws \Exception If PATCH fails.
	 */
	protected function patch_paypal_order( string $order_id, PayPalCart $cart ): void {
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
	protected function calculate_cart_totals( PayPalCart $cart ): array {
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
