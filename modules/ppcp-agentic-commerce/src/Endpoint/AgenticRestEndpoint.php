<?php
/**
 * Base class for all agentic commerce REST endpoints.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use JsonException;
use WC_REST_Controller;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\InternalServerError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidProduct;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\InvalidRequestError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\CartResponse;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;

/**
 * Base class for REST controllers in the agentic commerce module.
 */
abstract class AgenticRestEndpoint extends WC_REST_Controller {
	/**
	 * Endpoint namespace.
	 */
	protected const NAMESPACE = 'wc/v3/agentic';

	private JwtAuthService $auth_service;

	protected AgenticSessionHandler $session_handler;

	protected ResponseFactory $response_factory;

	public function __construct( JwtAuthService $auth_service, AgenticSessionHandler $session_handler, ResponseFactory $response_factory ) {
		$this->auth_service     = $auth_service;
		$this->session_handler  = $session_handler;
		$this->response_factory = $response_factory;
	}

	/**
	 * Verify JWT access.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error True if access is granted, otherwise a WP_Error object.
	 */
	public function check_permission( WP_REST_Request $request ) {
		$token   = $request->get_header( 'Authorization' );
		$context = $this->auth_service->validate_request( $token );

		if ( is_wp_error( $context ) ) {
			assert( $context instanceof WP_Error );

			return $context;
		}

		// TODO: verify the merchant details in $context.

		return true;
	}

	/**
	 * Successful API response, always returns cart details.
	 *
	 * @param CartResponse $cart The PayPalCart response object.
	 * @return WP_REST_Response The successful response.
	 */
	protected function cart_details( CartResponse $cart, int $status_code = 200 ): WP_REST_Response {
		return new WP_REST_Response( $cart->to_array(), $status_code );
	}

	/**
	 * Returns an error REST API response.
	 *
	 * @param AgenticError $error The error object.
	 * @return WP_REST_Response The error response.
	 */
	protected function error( AgenticError $error ): WP_REST_Response {
		return new WP_REST_Response( $error->to_array(), $error->get_status_code() );
	}

	/**
	 * Parses and validates JSON request body.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return array|AgenticError Parsed data or error response.
	 */
	protected function parse_json_body( WP_REST_Request $request ) {
		$body = $request->get_body();
		if ( empty( $body ) ) {
			return new InternalServerError( 'Request body is required' );
		}

		try {
			return json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			return new InternalServerError( 'Request body contains invalid JSON. Error: ' . $exception->getMessage() );
		}
	}

	/**
	 * Validate that all products in the cart exist in WooCommerce.
	 *
	 * @param PayPalCart $cart The cart to validate.
	 * @return array Array of InvalidProduct validation issues.
	 */
	protected function validate_products_exist( PayPalCart $cart ): array {
		$issues = array();

		foreach ( $cart->items() as $key => $item ) {
			$product_id = null;

			// Try to find product by variant_id first, then item_id.
			$item_identifier = $item->variant_id() ?: $item->item_id();
			if ( $item_identifier ) {
				// TODO We currently only send the id. Is this needed/desired?
				$product_id = wc_get_product_id_by_sku( $item_identifier );
			}

			// If no product found by SKU, try direct ID lookup.
			if ( ! $product_id && is_numeric( $item_identifier ) ) {
				$product    = wc_get_product( (int) $item_identifier );
				$product_id = $product ? $product->get_id() : null;
			}

			// If still no product found, create InvalidProduct issue.
			if ( ! $product_id ) {
				$field           = "items[{$key}]";
				$invalid_product = new InvalidProduct(
					"Product '{$item_identifier}' not found in WooCommerce catalog",
					"'{$item->name()}' not found in WooCommerce catalog",
					$field
				);
				$issues[]        = $invalid_product;
			}
		}

		return $issues;
	}
}
