<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

class PayPalJwkProvider {
	private ?array $cache = null;

	public function keys(): array {
		$cached = $this->cache_get();

		if ( null !== $cached ) {
			return $cached;
		}

		$keys = $this->fetch();
		$this->cache_set( $keys );

		return $keys;
	}

	protected function cache_get(): ?array {
		return $this->cache;
	}

	protected function cache_set( array $value ): void {
		$this->cache = $value;
	}

	protected function fetch(): array {
		return array();
	}
}
