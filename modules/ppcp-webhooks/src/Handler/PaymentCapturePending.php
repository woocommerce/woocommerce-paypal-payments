<?php
/**
 * Handles the Webhook PAYMENT.CAPTURE.PENDING
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WP_REST_Response;

/**
 * Class PaymentCaptureCompleted
 */
class PaymentCapturePending implements RequestHandler {

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * PaymentCaptureCompleted constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		LoggerInterface $logger
	) {
		$this->logger = $logger;
	}

	/**
	 * The event types a handler handles.
	 *
	 * @return string[]
	 */
	public function event_types(): array {
		return array( 'PAYMENT.CAPTURE.PENDING' );
	}

	/**
	 * Whether a handler is responsible for a given request or not.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function responsible_for_request( \WP_REST_Request $request ): bool {
		return in_array( $request['event_type'], $this->event_types(), true );
	}

	/**
	 * Responsible for handling the request.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_request( \WP_REST_Request $request ): WP_REST_Response {
		$response = array( 'success' => false );
		$resource = $request['resource'];
		if ( ! is_array( $resource ) ) {
			$message = 'Resource data not found in webhook request.';
			$this->logger->warning( $message, array( 'request' => $request ) );
			$response['message'] = $message;
			return new WP_REST_Response( $response );
		}

		$this->logger->info( (string) wc_print_r( $resource, true ) );

		$response['success'] = true;
		return new WP_REST_Response( $response );
	}
}
