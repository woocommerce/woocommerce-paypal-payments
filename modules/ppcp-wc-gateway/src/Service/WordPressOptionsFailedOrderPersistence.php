<?php
/**
 * WordPress Options Failed Order Persistence
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Service
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Service;

/**
 * Class WordPressOptionsFailedOrderPersistence
 */
class WordPressOptionsFailedOrderPersistence implements FailedOrderPersistenceInterface {

	const OPTION_KEY                 = 'ppcp_failed_orders';
	const MAX_FAILED_ORDERS_TO_STORE = 100;

	/**
	 * Store failed order transaction data.
	 *
	 * @param array $transaction_data The transaction data to store.
	 *
	 * @return void
	 */
	public function store_failed_order( array $transaction_data ): void {
		$failed_orders = get_option( self::OPTION_KEY, array() );

		$failed_orders[] = $transaction_data;

		if ( count( $failed_orders ) > self::MAX_FAILED_ORDERS_TO_STORE ) {
			$failed_orders = array_slice( $failed_orders, - self::MAX_FAILED_ORDERS_TO_STORE );
		}

		update_option( self::OPTION_KEY, $failed_orders );
	}

	/**
	 * Retrieve all stored failed orders.
	 *
	 * @return array Array of failed order transaction data.
	 */
	public function get_failed_orders(): array {
		return get_option( self::OPTION_KEY, array() );
	}

	/**
	 * Clear all stored failed orders.
	 *
	 * @return void
	 */
	public function clear_failed_orders(): void {
		delete_option( self::OPTION_KEY );
	}
}
