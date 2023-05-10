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
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class PaymentSaleRefunded
 */
class PaymentSaleRefunded implements RequestHandler {

	use TransactionIdHandlingTrait, RefundMetaTrait;

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
		$response = array( 'success' => false );
		if ( is_null( $request['resource'] ) ) {
			return new WP_REST_Response( $response );
		}

		$refund_id      = (string) ( $request['resource']['id'] ?? '' );
		$transaction_id = $request['resource']['sale_id'] ?? '';
		if ( ! $refund_id || ! $transaction_id ) {
			return new WP_REST_Response( $response );
		}

		$args      = array(
			'meta_query' => array(
				array(
					'key'     => '_transaction_id',
					'value'   => $transaction_id,
					'compare' => '=',
				),
			),
		);
		$wc_orders = wc_get_orders( $args );
		foreach ( $wc_orders as $wc_order ) {
			$refund = wc_create_refund(
				array(
					'order_id' => $wc_order->get_id(),
					'amount'   => $request['resource']['amount']['total_refunded_amount'],
				)
			);

			if ( is_wp_error( $refund ) ) {
				$this->logger->warning(
					sprintf(
					// translators: %s is the order id.
						__( 'Order %s could not be refunded', 'woocommerce-paypal-payments' ),
						(string) $wc_order->get_id()
					)
				);

				$response['message'] = $refund->get_error_message();
				return new WP_REST_Response( $response );
			}

			$this->logger->info(
				sprintf(
				// translators: %1$s is the order id %2$s is the amount which has been refunded.
					__(
						'Order %1$s has been refunded with %2$s through PayPal',
						'woocommerce-paypal-payments'
					),
					(string) $wc_order->get_id(),
					(string) $refund->get_amount()
				)
			);

			$this->update_transaction_id( $refund_id, $wc_order, $this->logger );
			$this->add_refund_to_meta( $wc_order, $refund_id );
		}

		$response['success'] = true;
		return new WP_REST_Response( $response );
	}
}
