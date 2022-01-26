<?php
/**
 * The customer repository.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Repository
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

/**
 * Class CustomerRepository
 */
class CustomerRepository {
	const CLIENT_ID_MAX_LENGTH = 22;

	/**
	 * The prefix.
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * CustomerRepository constructor.
	 *
	 * @param string $prefix The prefix.
	 */
	public function __construct( string $prefix ) {
		$this->prefix = $prefix;
	}

	/**
	 * Returns the customer ID for the given user ID.
	 *
	 * @param int $user_id The user ID.
	 * @return string
	 */
	public function customer_id_for_user( int $user_id ): string {
		if ( 0 === $user_id ) {
			$guest_customer_id = WC()->session->get( 'ppcp_guest_customer_id' );
			if ( is_string( $guest_customer_id ) && $guest_customer_id ) {
				return $guest_customer_id;
			}

			$unique_id = substr( $this->prefix . strrev( uniqid() ), 0, self::CLIENT_ID_MAX_LENGTH );
			WC()->session->set( 'ppcp_guest_customer_id', $unique_id );

			return $unique_id;
		}

		$guest_customer_id = get_user_meta( $user_id, 'ppcp_guest_customer_id', true );
		if ( $guest_customer_id ) {
			return $guest_customer_id;
		}

		return $this->prefix . (string) $user_id;
	}
}
