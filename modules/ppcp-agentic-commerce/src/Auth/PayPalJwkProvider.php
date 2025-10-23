<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Exception;

class PayPalJwkProvider {
	private const JWKS_URL = 'https://www.paypal.ai/.well-known/jwks.json';

	private ?Key $cache = null;

	public function keys(): ?Key {
		$keys = $this->cache_get();

		if ( null !== $keys ) {
			return $keys;
		}

		$keys = $this->fetch_key_material();
		if ( ! $keys ) {
			return null;
		}

		$this->cache_set( $keys );

		return $keys;
	}

	protected function cache_get(): ?Key {
		return $this->cache;
	}

	protected function cache_set( Key $value ): void {
		$this->cache = $value;
	}

	protected function fetch_key_material(): ?Key {
		$remove_user_agent =
			/**
			 * @param mixed|array  $args
			 * @param mixed|string $url
			 * @return mixed|array
			 */
			static function ( $args, $url ) {
				if ( is_array( $args ) && $url === self::JWKS_URL ) {
					$args['user-agent'] = '';
				}

				return $args;
			};

		add_filter( 'http_request_args', $remove_user_agent, 10, 2 );
		$response = wp_remote_get( self::JWKS_URL );
		remove_filter( 'http_request_args', $remove_user_agent, 10 );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		try {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );

			if ( ! is_array( $data ) || empty( $data['keys'] ) ) {
				return null;
			}

			$keys = JWK::parseKeySet( $data );

			// Return the first (and only) key from the keyset.
			return reset( $keys ) ?: null;
		} catch ( Exception $exception ) {
			return null;
		}
	}
}
