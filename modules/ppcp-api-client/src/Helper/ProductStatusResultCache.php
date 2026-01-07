<?php
/**
 * Caches the API results for the ProductStatus class.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

class ProductStatusResultCache {
	private array $cache = array();

	public function get( string $key ): string {
		return $this->cache[ $key ] ?? '';
	}

	public function set( string $key, string $value ): void {
		$this->cache[ $key ] = $value;
	}
}
