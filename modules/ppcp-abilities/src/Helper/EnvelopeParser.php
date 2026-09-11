<?php
/**
 * Parses the plugin's REST envelope for the abilities surface.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Helper;

use Psr\Log\LoggerInterface;

/**
 * Unwraps the plugin's `{ success, data, … }` REST envelope and converts a
 * failed envelope into a WP_Error.
 *
 * Security: with $redact_message (default), the envelope `message` and
 * `details` are logged server-side and replaced with a generic string —
 * backing endpoints propagate raw PayPalApiException text, whose
 * information_link URLs disclose internal API paths.
 *
 * @internal
 */
class EnvelopeParser {

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param LoggerInterface $logger The plugin's PSR-3 logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Unwrap the envelope to its inner `data`, or a WP_Error on success=false.
	 *
	 * @param mixed $payload        Decoded REST response.
	 * @param bool  $redact_message Redact + log the error message/details (default true).
	 * @return mixed Inner `data`, the original payload, or WP_Error on success=false.
	 */
	public function unwrap( $payload, bool $redact_message = true ) {
		if ( ! is_array( $payload ) ) {
			return $payload;
		}

		$envelope_error = $this->error_or_null( $payload, $redact_message );
		if ( null !== $envelope_error ) {
			return $envelope_error;
		}

		if ( array_key_exists( 'data', $payload ) ) {
			return $payload['data'];
		}

		return $payload;
	}

	/**
	 * The success=false branch, separate so callers whose endpoint returns
	 * extra top-level keys (e.g. CommonRestEndpoint's merchant/features) can
	 * reuse the redaction without having those keys discarded by `data`
	 * extraction.
	 *
	 * @param array $payload        Decoded REST envelope.
	 * @param bool  $redact_message See unwrap().
	 * @return \WP_Error|null WP_Error on success=false; null otherwise.
	 */
	public function error_or_null( array $payload, bool $redact_message = true ): ?\WP_Error {
		if ( ! array_key_exists( 'success', $payload ) || false !== $payload['success'] ) {
			return null;
		}

		$raw_message = isset( $payload['message'] ) && is_string( $payload['message'] )
			? $payload['message']
			: '';

		if ( $redact_message ) {
			if ( '' !== $raw_message ) {
				$this->logger->warning( '[ppcp-abilities] endpoint returned success=false: ' . $raw_message );
			}
			if ( isset( $payload['details'] ) ) {
				// Redact `details` like the message: log server-side, keep out of the agent payload.
				$this->logger->warning( '[ppcp-abilities] endpoint returned success=false details: ' . wp_json_encode( $payload['details'] ) );
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
}
