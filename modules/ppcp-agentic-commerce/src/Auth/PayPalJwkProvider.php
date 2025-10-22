<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use Firebase\JWT\Key;

class PayPalJwkProvider {
	private ?Key $cache = null;

	public function keys(): ?Key {
		$keys = $this->cache_get();

		if ( null !== $keys ) {
			return $keys;
		}

		$key_string = $this->fetch_key_material();
		if ( $key_string ) {
			$keys = new Key( $key_string, 'HS256' );
			$this->cache_set( $keys );
		}

		return $keys;
	}

	protected function cache_get(): ?Key {
		return $this->cache;
	}

	protected function cache_set( Key $value ): void {
		$this->cache = $value;
	}

	protected function fetch_key_material(): string {
		return 'test';
	}
}
