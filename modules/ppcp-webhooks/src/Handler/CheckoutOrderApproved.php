<?php
/**
 * Handles the Webhook CHECKOUT.ORDER.APPROVED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXOGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;

/**
 * Class CheckoutOrderApproved
 */
class CheckoutOrderApproved implements RequestHandler {

	use RequestHandlerTrait;

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
	 * CheckoutOrderApproved constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 * @param OrderEndpoint   $order_endpoint The order endpoint.
	 */
	public function __construct( LoggerInterface $logger, OrderEndpoint $order_endpoint ) {
		$this->logger         = $logger;
		$this->order_endpoint = $order_endpoint;
	}

	/**
	 * The event types a handler handles.
	 *
	 * @return string[]
	 */
	public function event_types(): array {
		return array(
			'CHECKOUT.ORDER.APPROVED',
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
		$custom_ids = $this->get_custom_ids_from_request( $request );
		if ( empty( $custom_ids ) ) {
			return $this->no_custom_ids_response( $request );
		}

		try {
			$order = isset( $request['resource']['id'] ) ?
				$this->order_endpoint->order( $request['resource']['id'] ) : null;
			if ( ! $order ) {
				$message = sprintf(
					'No paypal payment for webhook event %s was found.',
					isset( $request['id'] ) ? $request['id'] : ''
				);
				return $this->failure_response( $message );
			}

			if ( $order->intent() === 'CAPTURE' ) {
				$order = $this->order_endpoint->capture( $order );
			}
		} catch ( RuntimeException $error ) {
			$message = sprintf(
				'Could not capture payment for webhook event %s.',
				isset( $request['id'] ) ? $request['id'] : ''
			);
			return $this->failure_response( $message );
		}

		$wc_orders = $this->get_wc_orders_from_custom_ids( $custom_ids );
		if ( ! $wc_orders ) {
			return $this->no_wc_orders_response( $request );
		}

		foreach ( $wc_orders as $wc_order ) {
			if ( PayUponInvoiceGateway::ID === $wc_order->get_payment_method() || OXXOGateway::ID === $wc_order->get_payment_method() ) {
				continue;
			}

			if ( ! in_array( $wc_order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
				continue;
			}
			if ( $order->intent() === 'CAPTURE' ) {
				$wc_order->payment_complete();
			} else {
				$wc_order->update_status(
					'on-hold',
					__( 'Payment can be captured.', 'woocommerce-paypal-payments' )
				);
			}
			$this->logger->info(
				sprintf(
					'Order %s has been updated through PayPal',
					(string) $wc_order->get_id()
				)
			);
		}
		return $this->success_response();
	}
}
