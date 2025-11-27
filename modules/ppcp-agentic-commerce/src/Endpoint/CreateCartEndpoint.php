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

		// Create PayPal Order via PayPalOrderManager.
		try {
			$ec_token = $this->order_manager->create_order( $cart );
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
}
