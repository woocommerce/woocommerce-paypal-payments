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
		$unique_id = $this->generate_unique_id();
		if ( 0 === $user_id ) {
			$guest_customer_id = WC()->session->get( 'ppcp_guest_customer_id' );
			if ( is_string( $guest_customer_id ) && $guest_customer_id ) {
				return $guest_customer_id;
			}

			$unique_id = $this->generate_unique_id();
			WC()->session->set( 'ppcp_guest_customer_id', $unique_id );

			return $unique_id;
		}

		$guest_customer_id = get_user_meta( $user_id, 'ppcp_guest_customer_id', true );
		if ( $guest_customer_id ) {
			return $guest_customer_id;
		}

		return $this->prefix . (string) $user_id;
	}

	/**
	 * Generates a unique id based on the length of the prefix.
	 *
	 * @return string
	 */
	protected function generate_unique_id(): string {
		$offset = self::CLIENT_ID_MAX_LENGTH - strlen( $this->prefix );

		return strlen( uniqid() ) > $offset
			? $this->prefix . substr( uniqid(), $offset )
			: $this->prefix . uniqid();
	}
}
