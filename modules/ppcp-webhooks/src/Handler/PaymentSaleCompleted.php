<?php
/**
 * Handles the Webhook PAYMENT.SALE.COMPLETED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class PaymentSaleCompleted
 */
class PaymentSaleCompleted implements RequestHandler {

	use TransactionIdHandlingTrait, RequestHandlerTrait;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * PaymentSaleCompleted constructor.
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
		return array( 'PAYMENT.SALE.COMPLETED' );
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

		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return $this->failure_response( 'WooCommerce Subscriptions plugin is not active.' );
		}

		$billing_agreement_id = wc_clean( wp_unslash( $request['resource']['billing_agreement_id'] ?? '' ) );
		if ( ! $billing_agreement_id ) {
			return $this->failure_response( 'Could not retrieve billing agreement id for subscription.' );
		}

		$transaction_id = wc_clean( wp_unslash( $request['resource']['id'] ?? '' ) );
		if ( ! $transaction_id || ! is_string( $transaction_id ) ) {
			return $this->failure_response( 'Could not retrieve transaction id for subscription.' );
		}

		$args          = array(
			// phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_query' => array(
				array(
					'key'     => 'ppcp_subscription',
					'value'   => $billing_agreement_id,
					'compare' => '=',
				),
			),
		);
		$subscriptions = wcs_get_subscriptions( $args );
		foreach ( $subscriptions as $subscription ) {
			$is_renewal = $subscription->get_meta( '_ppcp_is_subscription_renewal' ) ?? '';
			if ( $is_renewal ) {
				$renewal_order = wcs_create_renewal_order( $subscription );
				if ( is_a( $renewal_order, WC_Order::class ) ) {
					$renewal_order->set_payment_method( $subscription->get_payment_method() );
					$renewal_order->payment_complete();
					$this->update_transaction_id( $transaction_id, $renewal_order, $this->logger );
					break;
				}
			}

			$parent_order = wc_get_order( $subscription->get_parent() );
			if ( is_a( $parent_order, WC_Order::class ) ) {
				$subscription->update_meta_data( '_ppcp_is_subscription_renewal', 'true' );
				$subscription->save_meta_data();
				$this->update_transaction_id( $transaction_id, $parent_order, $this->logger );
			}
		}

		return $this->success_response();
	}
}
