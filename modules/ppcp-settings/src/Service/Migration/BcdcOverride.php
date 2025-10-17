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

	private string $activate_reason = '';

	private string $deactivate_reason = '';

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

		$this->activate_reason = $reason;
		$this->is_active       = true;
	}

	public function deactivate( string $reason ): void {
		if ( ! $reason ) {
			return;
		}

		$this->deactivate_reason = $reason;
		$this->is_active         = false;
	}

	public function describe(): string {
		$info = array(
			'state'             => $this->is_active ? 'active' : 'inactive',
			'activate_reason'   => $this->activate_reason,
			'deactivate_reason' => $this->deactivate_reason,
		);

		return (string) wp_json_encode( $info );
	}
}
