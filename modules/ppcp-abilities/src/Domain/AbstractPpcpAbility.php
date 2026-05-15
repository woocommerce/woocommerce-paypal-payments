<?php
/**
 * Abstract base class for WooCommerce PayPal Payments ability definitions.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\PPCP;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;

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
	 * AbilitiesRegistrar::CATEGORY_SLUG so Domain classes can reference
	 * `self::CATEGORY_SLUG` without a cross-namespace static call.
	 */
	public const CATEGORY_SLUG = 'woocommerce';

	/**
	 * Shared PSR-3 logger for runtime error paths.
	 *
	 * Wired by AbilitiesModule::run() once container bootstrap is complete.
	 * Static because the Abilities API protocol requires callable arrays
	 * (`array(self::class, 'execute')`), which forces every Domain class
	 * onto static methods — there is no per-instance hook on which to
	 * inject dependencies. NullLogger default keeps the runtime safe if a
	 * helper is exercised before AbilitiesModule::run() fires (e.g. in
	 * tests that don't go through Modularity).
	 *
	 * Reserved for paths that run AFTER the container is healthy. The
	 * resolve_service() Throwable branch deliberately does NOT use this
	 * logger — its docblock explains why: the failure mode that path
	 * catches is "container not healthy enough to resolve services,"
	 * including the logger itself.
	 *
	 * @var LoggerInterface|null
	 */
	private static $logger = null;

	/**
	 * Inject the plugin's PSR-3 logger.
	 *
	 * Called from {@see \WooCommerce\PayPalCommerce\Abilities\AbilitiesModule::run()}
	 * once the container is bootstrapped.
	 *
	 * @internal
	 * @param LoggerInterface $logger The PSR-3 logger.
	 * @return void
	 */
	public static function set_logger( LoggerInterface $logger ): void {
		self::$logger = $logger;
	}

	/**
	 * Resolve the runtime logger, falling back to a NullLogger if the
	 * injection seam has not yet fired (defensive — production code paths
	 * always run after AbilitiesModule::run()).
	 *
	 * Visibility is `protected` so Domain subclasses inherit the helper.
	 *
	 * @return LoggerInterface
	 */
	protected static function logger(): LoggerInterface {
		if ( ! self::$logger instanceof LoggerInterface ) {
			self::$logger = new NullLogger();
		}

		return self::$logger;
	}

	/**
	 * Test-only seam: clear the injected logger so cross-test state does
	 * not bleed between assertions.
	 *
	 * @internal Test-isolation helper. Not part of the public API.
	 * @return void
	 */
	public static function reset_logger_for_testing(): void {
		self::$logger = null;
	}

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
	 * The error message in the envelope is REDACTED before it reaches the
	 * agent payload by default. Backing endpoints frequently call
	 * `return_error($e->getMessage())` which propagates raw
	 * `PayPalApiException` text — including PayPal-supplied
	 * `information_link` URLs that disclose internal API path segments
	 * and IDs. We log the raw text through the plugin's PSR-3 logger and
	 * substitute a generic message in the WP_Error returned to the caller.
	 * Pass `$redact_message = false` only when the caller is sure the
	 * endpoint's message is safe to surface (none of the current
	 * Shape-2 abilities meet that bar).
	 *
	 * Same philosophy applies to the envelope's optional `details` key:
	 * when `$redact_message = true`, `details` is DROPPED from the
	 * returned WP_Error data (it could carry structured PayPal API error
	 * bodies). When `$redact_message = false`, `details` passes through —
	 * the caller has already attested the response is safe. Future backing
	 * endpoints populating `details` with caller-facing structured error
	 * data must continue to call this helper with the redact default on.
	 *
	 * @param mixed $payload         Decoded REST response (array shape
	 *                               from rest_do_request()).
	 * @param bool  $redact_message  When true (default), the success=false
	 *                               envelope's `message` is replaced with
	 *                               a generic string and the original is
	 *                               written through the plugin's PSR-3
	 *                               logger.
	 * @return mixed The inner `data` value, or the original payload when it
	 *               is not an envelope, or a WP_Error on success=false.
	 */
	protected static function unwrap_envelope( $payload, bool $redact_message = true ) {
		if ( ! is_array( $payload ) ) {
			return $payload;
		}

		$envelope_error = self::envelope_error_or_null( $payload, $redact_message );
		if ( null !== $envelope_error ) {
			return $envelope_error;
		}

		if ( array_key_exists( 'data', $payload ) ) {
			return $payload['data'];
		}

		return $payload;
	}

	/**
	 * Inspect a REST envelope for the `success=false` failure case.
	 *
	 * Extracted from {@see self::unwrap_envelope()} so callers whose
	 * backing endpoint returns extra top-level keys alongside `data`
	 * (e.g. CommonRestEndpoint, which puts `merchant` and `features` at the
	 * top level) can reuse the redaction logic without having
	 * `unwrap_envelope()` discard those extras by extracting only `data`.
	 *
	 * @param array $payload         Decoded REST envelope.
	 * @param bool  $redact_message  See {@see self::unwrap_envelope()}.
	 * @return \WP_Error|null WP_Error when the envelope reports
	 *                        success=false; null on success or when the
	 *                        payload is not an envelope.
	 */
	protected static function envelope_error_or_null( array $payload, bool $redact_message = true ): ?\WP_Error {
		if ( ! array_key_exists( 'success', $payload ) || false !== $payload['success'] ) {
			return null;
		}

		$raw_message = isset( $payload['message'] ) && is_string( $payload['message'] )
			? $payload['message']
			: '';

		if ( $redact_message ) {
			if ( '' !== $raw_message ) {
				self::logger()->warning( '[ppcp-abilities] endpoint returned success=false: ' . $raw_message );
			}
			if ( isset( $payload['details'] ) ) {
				// Mirror message redaction: log `details` server-side instead
				// of forwarding to the agent. Future endpoints populating
				// `details` with PayPal API error bodies must rely on this
				// helper to keep them out of the agent payload.
				self::logger()->warning( '[ppcp-abilities] endpoint returned success=false details: ' . wp_json_encode( $payload['details'] ) );
			}

			return new \WP_Error(
				'woocommerce_paypal_payments_endpoint_error',
				__( 'PayPal Payments endpoint returned an error; see server log for details.', 'woocommerce-paypal-payments' )
			);
		}

		return new \WP_Error(
			'woocommerce_paypal_payments_endpoint_error',
			'' !== $raw_message
				? $raw_message
				: __( 'PayPal Payments endpoint returned an error.', 'woocommerce-paypal-payments' ),
			isset( $payload['details'] ) ? array( 'details' => $payload['details'] ) : array()
		);
	}

	/**
	 * Resolve a service from the plugin container, asserting its type.
	 *
	 * Shape-3 abilities (those that delegate to a PHP service rather than a
	 * REST route) all need the same resolver: ask the container, distinguish
	 * "container not initialized" (LogicException) from "container threw
	 * unexpectedly" (Throwable), assert the resolved value matches the
	 * expected type, and surface every failure mode as a structured
	 * WP_Error rather than letting it bubble.
	 *
	 * Unexpected exceptions are also written to error_log() so on-call has
	 * a server-side trace without needing to add instrumentation post-hoc.
	 * The plugin's PSR-3 logger is INTENTIONALLY not used here because the
	 * catch path may fire before the container is healthy enough to resolve
	 * any service — including the logger itself. The runtime error paths in
	 * unwrap_envelope() / GetOrderTracking::execute() / GetPaypalOrder::execute()
	 * DO flow through {@see self::logger()} because they always run after
	 * the container is warm; resolve_service() is the one bootstrap-stage
	 * exception, and the divergence is deliberate.
	 *
	 * Visibility is `protected` so Domain subclasses inherit the helper.
	 *
	 * @phpstan-template T of object
	 * @phpstan-param class-string<T> $expected_class
	 * @phpstan-return T|\WP_Error
	 *
	 * @param string $service_id     Container service id.
	 * @param string $expected_class Fully-qualified class the resolved service must implement / extend.
	 * @return object|\WP_Error An instance of $expected_class on success, otherwise WP_Error.
	 */
	protected static function resolve_service( string $service_id, string $expected_class ) {
		try {
			$service = PPCP::container()->get( $service_id );
		} catch ( LogicException $e ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_not_initialized',
				/* translators: %s: container service id. */
				sprintf( __( 'WooCommerce PayPal Payments is not initialized; service %s is unavailable.', 'woocommerce-paypal-payments' ), $service_id )
			);
		} catch ( Throwable $e ) {
			error_log( '[ppcp-abilities] resolve_service(' . $service_id . ') threw ' . get_class( $e ) . ': ' . $e->getMessage() );

			return new \WP_Error(
				'woocommerce_paypal_payments_service_unavailable',
				/* translators: %s: container service id. */
				sprintf( __( 'Service %s could not be resolved.', 'woocommerce-paypal-payments' ), $service_id )
			);
		}

		if ( ! $service instanceof $expected_class ) {
			error_log( '[ppcp-abilities] resolve_service(' . $service_id . ') returned unexpected type ' . ( is_object( $service ) ? get_class( $service ) : gettype( $service ) ) );

			return new \WP_Error(
				'woocommerce_paypal_payments_service_unavailable',
				/* translators: %s: container service id. */
				sprintf( __( 'Service %s returned an unexpected type.', 'woocommerce-paypal-payments' ), $service_id )
			);
		}

		return $service;
	}
}
