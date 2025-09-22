<?php
/**
 * Failed Order Tracker Service
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Service
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Service\FailedOrders;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

class FailedOrderTracker {

	/**
	 * The persistence layer for failed orders.
	 *
	 * @var FailedOrderPersistenceInterface
	 */
	private FailedOrderPersistenceInterface $persistence;

	/**
	 * FailedOrderTracker constructor.
	 *
	 * @param FailedOrderPersistenceInterface $persistence The persistence layer.
	 */
	public function __construct( FailedOrderPersistenceInterface $persistence ) {
		$this->persistence = $persistence;
	}

	/**
	 * Track failed card order transactions.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @param Order    $paypal_order The PayPal order.
	 *
	 * @return void
	 */
	public function track_failed_card_order( WC_Order $wc_order, Order $paypal_order ): void {
		if ( $this->is_failed_credit_card_transaction( $wc_order ) ) {
			$this->record_failed_transaction( $wc_order, $paypal_order );
		}
	}

	/**
	 * Get recent failed orders.
	 *
	 * @param int $limit Number of orders to retrieve.
	 *
	 * @return array
	 */
	public function get_recent_failed_orders( int $limit = 10 ): array {
		$failed_orders = $this->persistence->get_failed_orders();

		// Sort by timestamp descending (newest first).
		usort(
			$failed_orders,
			function ( $a, $b ) {
				return $b['timestamp'] - $a['timestamp'];
			}
		);

		return array_slice( $failed_orders, 0, $limit );
	}

	/**
	 * Get failed orders count for time period.
	 *
	 * @param int $minutes Time period in minutes.
	 *
	 * @return int
	 */
	public function get_failed_orders_count( int $minutes = 60 ): int {
		$failed_orders = $this->persistence->get_failed_orders();
		$cutoff_time   = current_time( 'timestamp' ) - ( $minutes * 60 );

		$recent_failures = array_filter(
			$failed_orders,
			function ( $order ) use ( $cutoff_time ) {
				return $order['timestamp'] > $cutoff_time;
			}
		);

		return count( $recent_failures );
	}

	/**
	 * Clear all stored failed orders (for testing).
	 *
	 * @return void
	 */
	public function clear_failed_orders(): void {
		$this->persistence->clear_failed_orders();
	}

	/**
	 * Check if this is a failed credit card transaction.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return bool
	 */
	private function is_failed_credit_card_transaction( WC_Order $order ): bool {
		return $order->has_status( 'failed' ) &&
				$order->get_payment_method() === CreditCardGateway::ID;
	}

	/**
	 * Record failed transaction details.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @param Order    $paypal_order The PayPal order.
	 *
	 * @return void
	 */
	private function record_failed_transaction( WC_Order $wc_order, Order $paypal_order ): void {
		$fraud_data = $wc_order->get_meta( PayPalGateway::FRAUD_RESULT_META_KEY );

		$failed_transaction_data = array(
			'timestamp'       => current_time( 'timestamp' ),
			'order_id'        => $wc_order->get_id(),
			'total'           => $wc_order->get_total(),
			'currency'        => $wc_order->get_currency(),
			'customer_ip'     => $wc_order->get_customer_ip_address(),
			'billing_email'   => $wc_order->get_billing_email(),
			'billing_country' => $wc_order->get_billing_country(),
			'fraud_data'      => $fraud_data,
			'paypal_order_id' => $paypal_order->id(),
			'failure_reason'  => $this->get_failure_reason( $wc_order ),
		);

		$this->persistence->store_failed_order( $failed_transaction_data );
	}

	/**
	 * Get failure reason from order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return string
	 */
	private function get_failure_reason( WC_Order $order ): string {
		$order_notes = wc_get_order_notes(
			array(
				'order_id' => $order->get_id(),
				'limit'    => 1,
			)
		);

		if ( ! empty( $order_notes ) ) {
			return $order_notes[0]->content;
		}

		return 'Payment failed';
	}
}
