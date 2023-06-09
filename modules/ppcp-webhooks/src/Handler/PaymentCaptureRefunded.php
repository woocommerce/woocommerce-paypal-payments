<?php
/**
 * Handels the Webhook PAYMENT.CAPTURE.REFUNDED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class PaymentCaptureRefunded
 */
class PaymentCaptureRefunded implements RequestHandler {

	use TransactionIdHandlingTrait, RefundMetaTrait, RequestHandlerTrait;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * PaymentCaptureRefunded constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * The event types a handler handles.
	 *
	 * @return string[]
	 */
	public function event_types(): array {
		return array( 'PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.AUTHORIZATION.VOIDED' );
	}

	/**
	 * Whether a handler is responsible for a given request or not.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function responsible_for_request( WP_REST_Request $request ): bool {
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
		$order_id  = isset( $request['resource']['custom_id'] ) ?
			$request['resource']['custom_id'] : 0;
		$refund_id = (string) ( $request['resource']['id'] ?? '' );
		if ( ! $order_id ) {
			$message = sprintf(
				'No order for webhook event %s was found.',
				isset( $request['id'] ) ? $request['id'] : ''
			);
			return $this->failure_response( $message );
		}

		$wc_order = wc_get_order( $order_id );
		if ( ! is_a( $wc_order, WC_Order::class ) ) {
			$message = sprintf(
				'Order for PayPal refund %s not found.',
				$refund_id
			);
			return $this->failure_response( $message );
		}

		$already_added_refunds = $this->get_refunds_meta( $wc_order );
		if ( in_array( $refund_id, $already_added_refunds, true ) ) {
			$this->logger->info( "Refund {$refund_id} is already handled." );
			return $this->success_response();
		}

		$refund = wc_create_refund(
			array(
				'order_id' => $wc_order->get_id(),
				'amount'   => $request['resource']['amount']['value'],
			)
		);
		if ( is_wp_error( $refund ) ) {
			assert( $refund instanceof WP_Error );
			$message = sprintf(
				'Order %1$s could not be refunded. %2$s',
				(string) $wc_order->get_id(),
				$refund->get_error_message()
			);

			return $this->failure_response( $message );
		}

		$this->logger->info(
			sprintf(
				'Order %1$s has been refunded with %2$s through PayPal',
				(string) $wc_order->get_id(),
				(string) $refund->get_amount()
			)
		);

		if ( $refund_id ) {
			$this->update_transaction_id( $refund_id, $wc_order, $this->logger );
			$this->add_refund_to_meta( $wc_order, $refund_id );
		}

		return $this->success_response();
	}
}
