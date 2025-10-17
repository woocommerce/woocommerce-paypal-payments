<?php
/**
 * The BCDC override flag.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

class BcdcOverride {
	private bool $is_active = false;

	/**
	 * Returns the current override state.
	 * True means, the merchant is in BCDC mode, regardless of PayPal's API response.
	 * False indicates no override is active and the API response should be used.
	 */
	public function is_active(): bool {
		return $this->is_active;
	}

	public function activate( string $reason ): void {
		if ( ! $reason ) {
			return;
		}

		$this->is_active = true;
	}

	public function deactivate( string $reason ): void {
		if ( ! $reason ) {
			return;
		}

		$this->is_active = false;
	}

	public function describe(): string {
		return 'inactive';
	}
}
