<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Exception;

/**
 * @see PayPalJwkProviderTest
 */
class PayPalJwkProvider {
	private const TRANSIENT_NAME = 'ppcp-ai-jwks';

	private const TRANSIENT_TTL = 24 * HOUR_IN_SECONDS;

	private const JWKS_URL = 'https://www.paypal.ai/.well-known/jwks.json';

	private ?Key $cache = null;

	public function keys(): ?Key {
		$keys = $this->cache_get();

		if ( null !== $keys ) {
			return $keys;
		}

		$keys = $this->fetch_key();
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

	protected function fetch_key(): ?Key {
		$jwks = get_transient( self::TRANSIENT_NAME );

		if ( ! is_array( $jwks ) || empty( $jwks['keys'] ) ) {
			$jwks = $this->fetch_jwks_from_remote();

			if ( is_array( $jwks ) && ! empty( $jwks['keys'] ) ) {
				set_transient( self::TRANSIENT_NAME, $jwks, self::TRANSIENT_TTL );
			} else {
				return null;
			}
		}

		try {
			$keys = JWK::parseKeySet( $jwks );

			return reset( $keys ) ?: null;
		} catch ( Exception $exception ) {
			return null;
		}
	}

	private function fetch_jwks_from_remote(): ?array {
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

			return $data;
		} catch ( Exception $exception ) {
			return null;
		}
	}
}
