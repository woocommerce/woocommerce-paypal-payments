<?php
/**
 * Failed Order Persistence Interface
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Service
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Service\FailedOrders;

interface FailedOrderPersistenceInterface {

	/**
	 * Store failed order transaction data.
	 *
	 * @param array $transaction_data The transaction data to store.
	 *
	 * @return void
	 */
	public function store_failed_order( array $transaction_data ): void;

	/**
	 * Retrieve all stored failed orders.
	 *
	 * @return array Array of failed order transaction data.
	 */
	public function get_failed_orders(): array;

	/**
	 * Clear all stored failed orders.
	 *
	 * @return void
	 */
	public function clear_failed_orders(): void;
}
