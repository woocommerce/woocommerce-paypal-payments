<?php
/**
 * Caches the API results for the ProductStatus class.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

class ProductStatusResultCache {
	private const CACHE_KEY = 'woocommerce-ppcp-cache-product-status';

	private bool $loaded = false;
	private array $cache = array();

	public function get( string $key ): string {
		$this->load();

		return $this->cache[ $key ] ?? '';
	}

	public function set( string $key, string $value ): void {
		$this->cache[ $key ] = $value;
	}

	public function clear( string $key ): void {
		unset( $this->cache[ $key ] );
	}

	private function load(): void {
		if ( $this->loaded ) {
			return;
		}
		$this->loaded = true;

		$data = get_transient( self::CACHE_KEY );

		if ( is_array( $data ) ) {
			$this->cache = array_map(
				static fn( $value ) => (string) $value,
				$data
			);
		}
	}
}
