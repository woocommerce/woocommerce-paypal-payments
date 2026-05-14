<?php
/**
 * Abstract base class for WooCommerce PayPal Payments ability definitions.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

/**
 * Shared helpers for PayPal Payments ability definitions.
 *
 * Mirrors the shape of Woo Core's
 * `Internal\Abilities\Domain\AbstractDomainAbility` (introduced in WC 10.9
 * via PR #64606) without coupling this plugin to that class — Woo Core's
 * lives under `Internal\`, which we treat as off-limits for cross-plugin
 * reuse. Update this base in sync if Woo Core's helper shape meaningfully
 * diverges.
 *
 * @internal
 */
abstract class AbstractPpcpAbility {

	/**
	 * Ability category slug shared across every PayPal Payments Domain ability.
	 *
	 * Hardcoded to `woocommerce` — Woo Core 10.9+ owns this category and
	 * registers it itself. Plugin ownership lives in the ability namespace
	 * (`woocommerce-paypal-payments/<name>`), not the category. Mirrors
	 * Abilities_Registrar::CATEGORY_SLUG so Domain classes can reference
	 * `self::CATEGORY_SLUG` without a cross-namespace static call.
	 */
	public const CATEGORY_SLUG = 'woocommerce';

	/**
	 * Execute a backing REST controller route and return its unwrapped response.
	 *
	 * Used by Domain classes that delegate to one of the plugin's existing
	 * wc/v3/wc_paypal/* REST routes. The helper builds a WP_REST_Request,
	 * dispatches it via rest_do_request(), and returns the inner data
	 * payload (or the WP_REST_Response on `$return_response`, so callers
	 * can read pagination headers).
	 *
	 * Visibility is `protected` so Domain subclasses inherit the helper.
	 *
	 * @param string $controller_class Fully-qualified backing controller class
	 *                                 (informational; surfaces a clear error when not loaded).
	 * @param string $method           HTTP method (GET, POST, PUT, DELETE).
	 * @param string $route            Resolved route path.
	 * @param array  $params           Request parameters.
	 * @param bool   $return_response  When true, return the WP_REST_Response object
	 *                                 so callers can read response headers (e.g. X-WP-Total).
	 * @return array|\WP_REST_Response|\WP_Error
	 */
	protected static function delegate_to_rest_controller(
		string $controller_class,
		string $method,
		string $route,
		array $params = array(),
		bool $return_response = false
	) {
		if ( ! class_exists( $controller_class ) ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_missing_controller',
				sprintf(
					/* translators: %s: fully-qualified class name of the missing REST controller. */
					__( 'REST controller %s is not loaded.', 'woocommerce-paypal-payments' ),
					$controller_class
				),
				array( 'status' => 500 )
			);
		}

		$request = new \WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response instanceof \WP_REST_Response ) {
			if ( $response->is_error() ) {
				$error = $response->as_error();
				if ( $error instanceof \WP_Error ) {
					return $error;
				}
			}
			if ( $return_response ) {
				return $response;
			}
			return $response->get_data();
		}

		return is_array( $response ) ? $response : array( $response );
	}

	/**
	 * Unwrap the plugin's standard `{ success: bool, data: …, …extras }`
	 * REST response envelope to the inner `data` payload.
	 *
	 * Every wc/v3/wc_paypal/* endpoint returns this envelope via
	 * RestEndpoint::return_success(). Agents care about the inner payload,
	 * not the wrapper. Returns a WP_Error when the envelope reports
	 * success=false so the failure surfaces structurally rather than
	 * as a magic `data: null` response.
	 *
	 * @param mixed $payload Decoded REST response (array shape from
	 *                       rest_do_request()).
	 * @return mixed The inner `data` value, or the original payload when it
	 *               is not an envelope, or a WP_Error on success=false.
	 */
	protected static function unwrap_envelope( $payload ) {
		if ( ! is_array( $payload ) ) {
			return $payload;
		}

		if ( array_key_exists( 'success', $payload ) && false === $payload['success'] ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_endpoint_error',
				isset( $payload['message'] ) && is_string( $payload['message'] )
					? $payload['message']
					: __( 'PayPal Payments endpoint returned an error.', 'woocommerce-paypal-payments' ),
				isset( $payload['details'] ) ? array( 'details' => $payload['details'] ) : array()
			);
		}

		if ( array_key_exists( 'data', $payload ) ) {
			return $payload['data'];
		}

		return $payload;
	}
}
