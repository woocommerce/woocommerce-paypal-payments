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
	const EXPIRATION = 864000; // 10 days.

	/**
	 * Returns a request ID based on the order ID.
	 *
	 * @param string $order_id The order ID.
	 *
	 * @return string
	 */
	public function get_for_order_id( string $order_id ): string {
		return $this->get( $order_id );
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
		return $this->set( $order->id(), $request_id );
	}

	/**
	 * Sets a request ID for the given key.
	 *
	 * @param string $key The key in the request ID storage.
	 * @param string $request_id The ID.
	 */
	public function set( string $key, string $request_id ): bool {
		return set_transient( self::KEY . '_' . $key, $request_id, self::EXPIRATION );
	}

	/**
	 * Returns a request ID.
	 *
	 * @param string $key The key in the request ID storage.
	 *
	 * @return string
	 */
	public function get( string $key ): string {
		$data_from_transient = get_transient( self::KEY . '_' . $key );
		if ( $data_from_transient ) {
			return $data_from_transient;
		}
		// No data in transient. The older system used to store data in one site option, so this key may be there:
		$all = $this->all();
		if ( count( $all ) < 1 ) {
			return '';
		}
		// We will clean up the legacy option for the time being.
		update_option( self::KEY, $this->cleanup( $all ) );
		return isset( $all[ $key ] ) ? (string) $all[ $key ]['id'] : '';
	}

	/**
	 * Return all IDs.
	 *
	 * @todo Remove this in next release sine we are now using transients.
	 * @return array
	 */
	private function all(): array {

		return (array) get_option( self::KEY, array() );
	}

	/**
	 * Clean up outdated request IDs.
	 *
	 * @param array $all All request IDs.
	 * @todo Remove this in next release sine we are now using transients.
	 *
	 * @return array
	 */
	private function cleanup( array $all ): array {
		$day_in_seconds = 86400;
		foreach ( $all as $order_id => $value ) {
			if ( ( time() + $day_in_seconds ) < $value['expiration'] ) {
				continue;
			}
			unset( $all[ $order_id ] );
		}
		return $all;
	}
}
