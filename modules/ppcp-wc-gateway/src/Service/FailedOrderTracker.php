<?php
/**
 * Failed Order Tracker Service
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Service
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Service;

use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class FailedOrderTracker
 */
class FailedOrderTracker {

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * FailedOrderTracker constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
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

		$this->store_failed_transaction( $failed_transaction_data );
		$this->log_failed_transaction( $failed_transaction_data );
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

	/**
	 * Store failed transaction in WordPress options.
	 *
	 * @param array $transaction_data The transaction data.
	 *
	 * @return void
	 */
	private function store_failed_transaction( array $transaction_data ): void {
		$failed_orders = get_option( 'ppcp_failed_orders', array() );

		$failed_orders[] = $transaction_data;

		// Keep only last 100 failed orders to prevent database bloat
		if ( count( $failed_orders ) > 100 ) {
			$failed_orders = array_slice( $failed_orders, -100 );
		}

		update_option( 'ppcp_failed_orders', $failed_orders );
	}

	/**
	 * Log failed transaction.
	 *
	 * @param array $transaction_data The transaction data.
	 *
	 * @return void
	 */
	private function log_failed_transaction( array $transaction_data ): void {
		$this->logger->warning(
			'Failed credit card transaction recorded',
			array(
				'order_id'        => $transaction_data['order_id'],
				'total'           => $transaction_data['total'],
				'paypal_order_id' => $transaction_data['paypal_order_id'],
				'avs_code'        => $transaction_data['fraud_data']['avs_code'] ?? 'N/A',
				'cvv_code'        => $transaction_data['fraud_data']['cvv2_code'] ?? 'N/A',
			)
		);
	}

	/**
	 * Get recent failed orders.
	 *
	 * @param int $limit Number of orders to retrieve.
	 *
	 * @return array
	 */
	public function get_recent_failed_orders( int $limit = 10 ): array {
		$failed_orders = get_option( 'ppcp_failed_orders', array() );

		// Sort by timestamp descending (newest first)
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
		$failed_orders = get_option( 'ppcp_failed_orders', array() );
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
		delete_option( 'ppcp_failed_orders' );
	}
}
