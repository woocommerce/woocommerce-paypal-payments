<?php
/**
 * Handles CHECKOUT.PAYMENT-APPROVAL.REVERSED Webhook.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class CheckoutPaymentApprovalReversed
 */
class CheckoutPaymentApprovalReversed implements RequestHandler {

	use RequestHandlerTrait;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * CheckoutPaymentApprovalReversed constructor.
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
		return array(
			'CHECKOUT.PAYMENT-APPROVAL.REVERSED',
		);
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
		$custom_ids = $this->get_wc_order_ids_from_request( $request );
		if ( empty( $custom_ids ) ) {
			return $this->no_custom_ids_response( $request );
		}

		$wc_orders = $this->get_wc_orders_from_custom_ids( $custom_ids );
		if ( ! $wc_orders ) {
			return $this->no_wc_orders_response( $request );
		}

		foreach ( $wc_orders as $wc_order ) {
			if ( in_array( $wc_order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
				$error_message = sprintf(
					'Failed to capture order %1$s through PayPal.',
					(string) $wc_order->get_id()
				);

				$this->logger->warning( 'CHECKOUT.PAYMENT-APPROVAL.REVERSED received. ' . $error_message );

				$wc_order->update_status( 'failed', $error_message );
			}
		}

		return $this->success_response();
	}
}
