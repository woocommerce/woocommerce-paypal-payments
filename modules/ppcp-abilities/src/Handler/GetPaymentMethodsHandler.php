<?php
/**
 * Handler for the get-payment-methods ability.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Handler;

use Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\Abilities\Helper\EnvelopeParser;
use WooCommerce\PayPalCommerce\Settings\Endpoint\PaymentRestEndpoint;

/**
 * Lists every PayPal payment gateway with enabled state, dependency edges and
 * warnings. Calls the injected PaymentRestEndpoint directly.
 *
 * @internal
 */
class GetPaymentMethodsHandler {

	/**
	 * @var PaymentRestEndpoint
	 */
	private $endpoint;

	/**
	 * @var EnvelopeParser
	 */
	private $envelope;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param PaymentRestEndpoint $endpoint The backing payment-details endpoint.
	 * @param EnvelopeParser      $envelope The shared envelope parser.
	 * @param LoggerInterface     $logger   The plugin's PSR-3 logger.
	 */
	public function __construct( PaymentRestEndpoint $endpoint, EnvelopeParser $envelope, LoggerInterface $logger ) {
		$this->endpoint = $endpoint;
		$this->envelope = $envelope;
		$this->logger   = $logger;
	}

	/**
	 * Execute callback.
	 *
	 * @param mixed $input Optional; ignored.
	 * @return array|\WP_Error The payment-methods payload or WP_Error on failure.
	 */
	public function execute( $input = null ) {
		unset( $input );

		try {
			$response = $this->endpoint->get_details();
		} catch ( Throwable $e ) {
			$this->logger->error( '[ppcp-abilities] get-payment-methods lookup threw ' . get_class( $e ) . ': ' . $e->getMessage() );

			return new \WP_Error(
				'woocommerce_paypal_payments_endpoint_error',
				__( 'PayPal Payments endpoint returned an error; see server log for details.', 'woocommerce-paypal-payments' )
			);
		}

		$payload = $response instanceof \WP_REST_Response ? $response->get_data() : $response;

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$unwrapped = $this->envelope->unwrap( $payload );

		if ( is_wp_error( $unwrapped ) ) {
			return $unwrapped;
		}

		return is_array( $unwrapped ) ? $unwrapped : array( 'data' => $unwrapped );
	}
}
