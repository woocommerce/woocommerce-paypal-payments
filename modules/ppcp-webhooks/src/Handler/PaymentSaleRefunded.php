<?php
/**
 * Handles the Webhook PAYMENT.SALE.REFUNDED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class PaymentSaleRefunded
 */
class PaymentSaleRefunded implements RequestHandler {

	use TransactionIdHandlingTrait, RefundMetaTrait, RequestHandlerTrait;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * PaymentSaleRefunded constructor.
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
		return array( 'PAYMENT.SALE.REFUNDED' );
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
		if ( is_null( $request['resource'] ) ) {
			return $this->failure_response();
		}

		$refund_id             = (string) ( $request['resource']['id'] ?? '' );
		$transaction_id        = $request['resource']['sale_id'] ?? '';
		$total_refunded_amount = $request['resource']['total_refunded_amount']['value'] ?? '';
		if ( ! $refund_id || ! $transaction_id || ! $total_refunded_amount ) {
			return $this->failure_response();
		}

		$args = array(
			// phpcs:disable WordPress.DB.SlowDBQuery
			'meta_key'     => '_transaction_id',
			'meta_value'   => $transaction_id,
			'meta_compare' => '=',
			// phpcs:enable
		);
		$wc_orders = wc_get_orders( $args );

		if ( ! is_array( $wc_orders ) ) {
			return $this->failure_response();
		}

		foreach ( $wc_orders as $wc_order ) {
			$refund = wc_create_refund(
				array(
					'order_id' => $wc_order->get_id(),
					'amount'   => $total_refunded_amount,
				)
			);

			if ( $refund instanceof WP_Error ) {
				$message = sprintf(
					'Order %s could not be refunded. %s',
					(string) $wc_order->get_id(),
					$refund->get_error_message()
				);

				return $this->failure_response( $message );
			}

			$order_refunded_message = sprintf(
				'Order %1$s has been refunded with %2$s through PayPal.',
				(string) $wc_order->get_id(),
				(string) $total_refunded_amount
			);
			$this->logger->info( $order_refunded_message );
			$wc_order->add_order_note( $order_refunded_message );

			$this->update_transaction_id( $refund_id, $wc_order, $this->logger );
			$this->add_refund_to_meta( $wc_order, $refund_id );
		}

		return $this->success_response();
	}
}
