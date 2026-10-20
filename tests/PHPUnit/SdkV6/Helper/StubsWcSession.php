<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use Mockery;
use WC_Session;
use function Brain\Monkey\Functions\when;

/**
 * A WC()->session backed by a plain array, so what one call stores is visible to
 * the next, the way the real session persists across a request.
 */
trait StubsWcSession {

	/**
	 * @param array<string, mixed> $store The backing store.
	 */
	private function session_with( array &$store ): WC_Session {
		$session = Mockery::mock( WC_Session::class );

		$session->shouldReceive( 'get' )->andReturnUsing(
			static function ( string $key ) use ( &$store ) {
				return $store[ $key ] ?? null;
			}
		);

		$session->shouldReceive( 'set' )->andReturnUsing(
			static function ( string $key, $value ) use ( &$store ): void {
				$store[ $key ] = $value;
			}
		);

		return $session;
	}
}
