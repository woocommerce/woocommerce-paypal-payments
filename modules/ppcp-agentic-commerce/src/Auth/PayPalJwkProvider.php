<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use Firebase\JWT\Key;

class PayPalJwkProvider {
	private const ALGORITHM = 'HS256';

	private ?Key $cache = null;

	public function keys(): ?Key {
		$keys = $this->cache_get();

		if ( null !== $keys ) {
			return $keys;
		}

		$key_string = $this->fetch_key_material();
		if ( ! $key_string ) {
			return null;
		}

		$keys = new Key( $key_string, self::ALGORITHM );
		$this->cache_set( $keys );

		return $keys;
	}

	protected function cache_get(): ?Key {
		return $this->cache;
	}

	protected function cache_set( Key $value ): void {
		$this->cache = $value;
	}

	protected function fetch_key_material(): string {
		// todo - the string should be fetched from a .well-known URL.
		return <<<'EOF'
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvv7Pi1nWWrJj4n5+6gX9
B7BQpctaPEg9VdVK1kzc9xBNwZobeWEgEmiUGtkrn8S5R6Q4NmB4hnb8F5jeCX5O
kyA49mgzw4wNXUPGTGMY5Eoxt9zu1Heaivkljh4+wN6d01oIFkHT6E7VjEJOG2RA
49t7fgQ1phJIUK39B0RAXIG2pYicbujeiiJ12iQipMjY/TVD0KZgUc2Vj2apk7Dv
1YBqFG+HlSG5hWu880IzGQE9Pds5qekIawJJyed08otq29hDHlFd28B0fFhdzcu8
cN83NxddXBlh77b8+a7gaWC5/Iw45THRpIsiG41uX0r0INEDcnR3qCUkz6m9LOVW
kQIDAQAB
EOF;
	}
}
