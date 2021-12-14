<?php
/**
 * Handles the Webhook CHECKOUT.ORDER.COMPLETED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;

/**
 * Class CheckoutOrderCompleted
 */
class CheckoutOrderCompleted implements RequestHandler {

	use PrefixTrait;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * CheckoutOrderCompleted constructor.
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
		return array(
			'CHECKOUT.ORDER.COMPLETED',
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
		$response   = array( 'success' => false );
		$custom_ids = array_filter(
			array_map(
				static function ( array $purchase_unit ): string {
					return isset( $purchase_unit['custom_id'] ) ?
						(string) $purchase_unit['custom_id'] : '';
				},
				isset( $request['resource']['purchase_units'] ) ?
					(array) $request['resource']['purchase_units'] : array()
			),
			static function ( string $order_id ): bool {
				return ! empty( $order_id );
			}
		);

		if ( empty( $custom_ids ) ) {
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

		$order_ids = array_map(
			array(
				$this,
				'sanitize_custom_id',
			),
			$custom_ids
		);
		$args      = array(
			'post__in' => $order_ids,
			'limit'    => -1,
		);
		$wc_orders = wc_get_orders( $args );
		if ( ! $wc_orders ) {
			$message = sprintf(
			// translators: %s is the PayPal order Id.
				__( 'Order for PayPal order %s not found.', 'woocommerce-paypal-payments' ),
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

		foreach ( $wc_orders as $wc_order ) {
			if ( ! in_array( $wc_order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
				continue;
			}
			$wc_order->payment_complete();
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
		}
		$response['success'] = true;
		return rest_ensure_response( $response );
	}
	// phpcs:enable Inpsyde.CodeQuality.FunctionLength.TooLong
}
