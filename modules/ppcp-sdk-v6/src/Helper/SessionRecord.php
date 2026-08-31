<?php
/**
 * One thing remembered for the duration of a wallet payment.
 *
 * The amount PayPal is charged is built from the cart in a later request, so a
 * sheet's decisions have to outlive the request that made them. They must not
 * outlive the payment: one an abandoned sheet left behind would price the next.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

abstract class SessionRecord {

	protected const SESSION_KEY = '';

	// The backstop for a shopper who walks away: outlasts a refused payment and its
	// retry, but not the rest of their session.
	private const TTL = 900;

	/**
	 * Drops the record, so the shopper's own choices apply again.
	 */
	public function forget(): void {
		$session = WC()->session;
		if ( $session ) {
			$session->set( static::SESSION_KEY, null );
		}
	}

	/**
	 * Remembers a value for the rest of this payment.
	 *
	 * @param mixed $value The value to remember.
	 */
	protected function remember( $value ): void {
		$session = WC()->session;
		if ( ! $session ) {
			return;
		}

		$session->set(
			static::SESSION_KEY,
			array(
				'value'   => $value,
				'expires' => time() + self::TTL,
			)
		);
	}

	/**
	 * The remembered value, or null when there is none left to honour.
	 *
	 * @return mixed
	 */
	protected function remembered() {
		$session = WC()->session;
		$record  = $session ? $session->get( static::SESSION_KEY ) : null;

		if ( ! is_array( $record ) || ! isset( $record['value'], $record['expires'] ) ) {
			return null;
		}

		if ( $record['expires'] < time() ) {
			$this->forget();

			return null;
		}

		return $record['value'];
	}
}
