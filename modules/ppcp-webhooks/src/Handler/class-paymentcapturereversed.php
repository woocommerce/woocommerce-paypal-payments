<?php
/**
 * Handles the following Webhooks:
 * - PAYMENT.CAPTURE.REVERSED
 * - PAYMENT.ORDER.CANCELLED
 * - PAYMENT.CAPTURE.DENIED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;

/**
 * Class PaymentCaptureReversed
 */
class PaymentCaptureReversed implements RequestHandler {

	use PrefixTrait;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * PaymentCaptureReversed constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 * @param string          $prefix The prefix.
	 */
	public function __construct( LoggerInterface $logger, string $prefix ) {
		$this->logger = $logger;
		$this->prefix = $prefix;
	}

	/**
	 * The event types a handler handles.
	 *
	 * @return array
	 */
	public function event_types(): array {
		return array(
			'PAYMENT.CAPTURE.REVERSED',
			'PAYMENT.ORDER.CANCELLED',
			'PAYMENT.CAPTURE.DENIED',
		);
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
	 * @return \WP_REST_Response
	 */
	public function handle_request( \WP_REST_Request $request ): \WP_REST_Response {
		$response = array( 'success' => false );
		$order_id = isset( $request['resource']['custom_id'] ) ?
			$this->sanitize_custom_id( $request['resource']['custom_id'] ) : 0;
		if ( ! $order_id ) {
			$message = sprintf(
				// translators: %s is the PayPal webhook Id.
				__(
					'No order for webhook event %s was found.',
					'woocommerce-paypal-payments'
				),
				isset( $request['id'] ) ? $request['id'] : ''
			);
			$this->logger->log(
				'warning',
				$message,
				array(
					'request' => $request,
				)
			);
			$response['message'] = $message;
			return rest_ensure_response( $response );
		}

		$wc_order = wc_get_order( $order_id );
		if ( ! is_a( $wc_order, \WC_Order::class ) ) {
			$message = sprintf(
			// translators: %s is the PayPal refund Id.
				__( 'Order for PayPal refund %s not found.', 'woocommerce-paypal-payments' ),
				isset( $request['resource']['id'] ) ? $request['resource']['id'] : ''
			);
			$this->logger->log(
				'warning',
				$message,
				array(
					'request' => $request,
				)
			);
			$response['message'] = $message;
			return rest_ensure_response( $response );
		}

		/**
		 * The WooCommerce order.
		 *
		 * @var \WC_Order $wc_order
		 */
		$response['success'] = (bool) $wc_order->update_status( 'cancelled' );

		$message = $response['success'] ? sprintf(
			// translators: %1$s is the order id.
			__(
				'Order %1$s has been cancelled through PayPal',
				'woocommerce-paypal-payments'
			),
			(string) $wc_order->get_id()
		) : sprintf(
			// translators: %1$s is the order id.
			__( 'Failed to cancel order %1$s through PayPal', 'woocommerce-paypal-payments' ),
			(string) $wc_order->get_id()
		);
		$this->logger->log(
			$response['success'] ? 'info' : 'warning',
			$message,
			array(
				'request' => $request,
				'order'   => $wc_order,
			)
		);
		return rest_ensure_response( $response );
	}
}
