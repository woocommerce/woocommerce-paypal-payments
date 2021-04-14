<?php
/**
 * Handles the Webhook PAYMENT.CAPTURE.COMPLETED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Psr\Log\LoggerInterface;

/**
 * Class PaymentCaptureCompleted
 */
class PaymentCaptureCompleted implements RequestHandler {

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
		return array( 'PAYMENT.CAPTURE.COMPLETED' );
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

		if ( $wc_order->get_status() !== 'on-hold' ) {
			$response['success'] = true;
			return rest_ensure_response( $response );
		}
		$wc_order->add_order_note(
			__( 'Payment successfully captured.', 'woocommerce-paypal-payments' )
		);

		$wc_order->payment_complete();
		$wc_order->update_meta_data( PayPalGateway::CAPTURED_META_KEY, 'true' );
		$wc_order->save();
		$this->logger->log(
			'info',
			sprintf(
			// translators: %s is the order ID.
				__(
					'Order %s has been updated through PayPal',
					'woocommerce-paypal-payments'
				),
				(string) $wc_order->get_id()
			),
			array(
				'request' => $request,
				'order'   => $wc_order,
			)
		);
		$response['success'] = true;
		return rest_ensure_response( $response );
	}
}
