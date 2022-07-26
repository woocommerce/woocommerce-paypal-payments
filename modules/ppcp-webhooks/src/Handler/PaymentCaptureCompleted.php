<?php
/**
 * Handles the Webhook PAYMENT.CAPTURE.COMPLETED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Exception;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WP_REST_Response;

/**
 * Class PaymentCaptureCompleted
 */
class PaymentCaptureCompleted implements RequestHandler {

	use PrefixTrait, TransactionIdHandlingTrait;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * PaymentCaptureCompleted constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 * @param string          $prefix The prefix.
	 * @param OrderEndpoint   $order_endpoint The order endpoint.
	 */
	public function __construct(
		LoggerInterface $logger,
		string $prefix,
		OrderEndpoint $order_endpoint
	) {
		$this->logger         = $logger;
		$this->prefix         = $prefix;
		$this->order_endpoint = $order_endpoint;
	}

	/**
	 * The event types a handler handles.
	 *
	 * @return string[]
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
	 * @return WP_REST_Response
	 */
	public function handle_request( \WP_REST_Request $request ): WP_REST_Response {
		$response = array( 'success' => false );

		$webhook_id = (string) ( $request['id'] ?? '' );

		$resource = $request['resource'];
		if ( ! is_array( $resource ) ) {
			$message = 'Resource data not found in webhook request.';
			$this->logger->warning( $message, array( 'request' => $request ) );
			$response['message'] = $message;
			return new WP_REST_Response( $response );
		}

		$wc_order_id = isset( $resource['custom_id'] ) ?
			$this->sanitize_custom_id( (string) $resource['custom_id'] ) : 0;
		if ( ! $wc_order_id ) {
			$message = sprintf( 'No order for webhook event %s was found.', $webhook_id );
			$this->logger->warning( $message, array( 'request' => $request ) );
			$response['message'] = $message;
			return new WP_REST_Response( $response );
		}

		$wc_order = wc_get_order( $wc_order_id );
		if ( ! is_a( $wc_order, \WC_Order::class ) ) {
			$message = sprintf( 'No order for webhook event %s was found.', $webhook_id );
			$this->logger->warning( $message, array( 'request' => $request ) );
			$response['message'] = $message;
			return new WP_REST_Response( $response );
		}

		$order_id = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

		/**
		 * Allow access to the webhook logic before updating the WC order.
		 */
		do_action( 'ppcp_payment_capture_completed_webhook_handler', $wc_order, $order_id );

		if ( $wc_order->get_status() !== 'on-hold' ) {
			$response['success'] = true;
			return new WP_REST_Response( $response );
		}
		$wc_order->add_order_note(
			__( 'Payment successfully captured.', 'woocommerce-paypal-payments' )
		);

		$wc_order->payment_complete();
		$wc_order->update_meta_data( AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'true' );
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

		if ( $order_id ) {
			try {
				$order = $this->order_endpoint->order( (string) $order_id );

				$transaction_id = $this->get_paypal_order_transaction_id( $order );
				if ( $transaction_id ) {
					$this->update_transaction_id( $transaction_id, $wc_order, $this->logger );
				}
			} catch ( Exception $exception ) {
				$this->logger->warning( 'Failed to get transaction ID: ' . $exception->getMessage() );
			}
		}

		$response['success'] = true;
		return new WP_REST_Response( $response );
	}
}
