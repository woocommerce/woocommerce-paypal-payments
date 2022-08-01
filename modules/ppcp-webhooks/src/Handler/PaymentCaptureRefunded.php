<?php
/**
 * Handels the Webhook PAYMENT.CAPTURE.REFUNDED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class PaymentCaptureRefunded
 */
class PaymentCaptureRefunded implements RequestHandler {

	use PrefixTrait, TransactionIdHandlingTrait, RefundMetaTrait;

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
	 * @param string          $prefix The prefix.
	 */
	public function __construct( LoggerInterface $logger, string $prefix ) {
		$this->logger = $logger;
		$this->prefix = $prefix;
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
		$response  = array( 'success' => false );
		$order_id  = isset( $request['resource']['custom_id'] ) ?
			$this->sanitize_custom_id( $request['resource']['custom_id'] ) : 0;
		$refund_id = (string) ( $request['resource']['id'] ?? '' );
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
			return new WP_REST_Response( $response );
		}

		$wc_order = wc_get_order( $order_id );
		if ( ! is_a( $wc_order, \WC_Order::class ) ) {
			$message = sprintf(
			// translators: %s is the PayPal refund Id.
				__( 'Order for PayPal refund %s not found.', 'woocommerce-paypal-payments' ),
				$refund_id
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

		$already_added_refunds = $this->get_refunds_meta( $wc_order );
		if ( in_array( $refund_id, $already_added_refunds, true ) ) {
			$this->logger->info( "Refund {$refund_id} is already handled." );
			return new WP_REST_Response( $response );
		}

		/**
		 * The WooCommerce order.
		 *
		 * @var \WC_Order $wc_order
		 */
		$refund = wc_create_refund(
			array(
				'order_id' => $wc_order->get_id(),
				'amount'   => $request['resource']['amount']['value'],
			)
		);
		if ( is_wp_error( $refund ) ) {
			$this->logger->log(
				'warning',
				sprintf(
					// translators: %s is the order id.
					__( 'Order %s could not be refunded', 'woocommerce-paypal-payments' ),
					(string) $wc_order->get_id()
				),
				array(
					'request' => $request,
					'error'   => $refund,
				)
			);

			$response['message'] = $refund->get_error_message();
			return new WP_REST_Response( $response );
		}

		$this->logger->log(
			'info',
			sprintf(
				// translators: %1$s is the order id %2$s is the amount which has been refunded.
				__(
					'Order %1$s has been refunded with %2$s through PayPal',
					'woocommerce-paypal-payments'
				),
				(string) $wc_order->get_id(),
				(string) $refund->get_amount()
			),
			array(
				'request' => $request,
				'order'   => $wc_order,
			)
		);

		if ( $refund_id ) {
			$this->update_transaction_id( $refund_id, $wc_order, $this->logger );
			$this->add_refund_to_meta( $wc_order, $refund_id );
		}

		$response['success'] = true;
		return new WP_REST_Response( $response );
	}
}
