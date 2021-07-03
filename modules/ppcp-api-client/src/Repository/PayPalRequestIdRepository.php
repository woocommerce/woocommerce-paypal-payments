<?php
/**
 * The repository for the request IDs.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Repository
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;

/**
 * Class PayPalRequestIdRepository
 */
class PayPalRequestIdRepository {

	const KEY = 'ppcp-request-ids';

	/**
	 * Returns a request ID based on the order ID.
	 *
	 * @param string $order_id The order ID.
	 *
	 * @return string
	 */
	public function get_for_order_id( string $order_id ): string {
		$all = $this->all();
		return isset( $all[ $order_id ] ) ? (string) $all[ $order_id ]['id'] : '';
	}

	/**
	 * Returns the request ID for an order.
	 *
	 * @param Order $order The order.
	 *
	 * @return string
	 */
	public function get_for_order( Order $order ): string {
		return $this->get_for_order_id( $order->id() );
	}

	/**
	 * Sets a request ID for a specific order.
	 *
	 * @param Order  $order The order.
	 * @param string $request_id The ID.
	 *
	 * @return bool
	 */
	public function set_for_order( Order $order, string $request_id ): bool {
		$all                 = $this->all();
		$all[ $order->id() ] = array(
			'id'         => $request_id,
			'expiration' => time() + 10 * DAY_IN_SECONDS,
		);
		$all                 = $this->cleanup( $all );
		update_option( self::KEY, $all );
		return true;
	}

	/**
	 * Return all IDs.
	 *
	 * @return array
	 */
	private function all(): array {

		return (array) get_option( 'ppcp-request-ids', array() );
	}

	/**
	 * Clean up outdated request IDs.
	 *
	 * @param array $all All request IDs.
	 *
	 * @return array
	 */
	private function cleanup( array $all ): array {

		foreach ( $all as $order_id => $value ) {
			if ( time() < $value['expiration'] ) {
				continue;
			}
			unset( $all[ $order_id ] );
		}
		return $all;
	}
}
