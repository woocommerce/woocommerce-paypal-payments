<?php
/**
 * Handles the Webhook PAYMENT.CAPTURE.PENDING
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class PaymentCaptureCompleted
 */
class PaymentCapturePending implements RequestHandler {

	use PrefixTrait;

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
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_request( WP_REST_Request $request ): WP_REST_Response {
		$response = array( 'success' => false );
		$order_id = $request['resource'] !== null && isset( $request['resource']['custom_id'] )
			? $this->sanitize_custom_id( $request['resource']['custom_id'] )
			: 0;
		if ( ! $order_id ) {
			$message = sprintf(
			// translators: %s is the PayPal webhook Id.
				__(
					'No order for webhook event %s was found.',
					'woocommerce-paypal-payments'
				),
				$request['id'] !== null && isset( $request['id'] ) ? $request['id'] : ''
			);
			$this->logger->log(
				'warning',
				$message,
				array(
					'request' => $request,
				)
			);
			$response['message'] = $message;
			return new WP_REST_Response( $response );
		}

		$resource = $request['resource'];
		if ( ! is_array( $resource ) ) {
			$message = 'Resource data not found in webhook request.';
			$this->logger->warning( $message, array( 'request' => $request ) );
			$response['message'] = $message;
			return new WP_REST_Response( $response );
		}

		$wc_order = wc_get_order( $order_id );
		if ( ! is_a( $wc_order, \WC_Order::class ) ) {
			$message = sprintf(
				'WC order for PayPal ID %s not found.',
				$request['resource'] !== null && isset( $request['resource']['id'] ) ? $request['resource']['id'] : ''
			);

			$this->logger->warning( $message );

			$response['message'] = $message;
			return new WP_REST_Response( $response );
		}

		if ( $wc_order->get_status() === 'pending' ) {
			$wc_order->update_status( 'on-hold', __( 'Payment initiation was successful, and is waiting for the buyer to complete the payment.', 'woocommerce-paypal-payments' ) );

		}

		$response['success'] = true;
		return new WP_REST_Response( $response );
	}
}
