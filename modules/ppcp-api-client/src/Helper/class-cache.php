<?php
/**
 * Manages caching of values.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

/**
 * Class Cache
 */
class Cache {

	/**
	 * The prefix for the value keys.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Cache constructor.
	 *
	 * @param string $prefix The prefix for the value keys.
	 */
	public function __construct( string $prefix ) {
		$this->prefix = $prefix;
	}

	/**
	 * Gets a value.
	 *
	 * @param string $key The key under which the value is stored.
	 *
	 * @return mixed
	 */
	public function get( string $key ) {
		return get_transient( $this->prefix . $key );
	}

	/**
	 * Caches a value.
	 *
	 * @param string $key The key under which the value should be cached.
	 * @param mixed  $value The value to cache.
	 *
	 * @return bool
	 */
	public function set( string $key, $value ): bool {
		return (bool) set_transient( $this->prefix . $key, $value );
	}
}
