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

	private string $activate_time = '';

	private string $deactivate_time = '';

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
		if ( ! $reason || $this->is_active ) {
			return;
		}

		$this->activate_reason   = $reason;
		$this->activate_time     = gmdate( 'Y-m-d H:i:s' );
		$this->deactivate_reason = '';
		$this->deactivate_time   = '';
		$this->is_active         = true;
	}

	public function deactivate( string $reason ): void {
		if ( ! $reason || ! $this->is_active ) {
			return;
		}

		$this->deactivate_reason = $reason;
		$this->deactivate_time   = gmdate( 'Y-m-d H:i:s' );
		$this->is_active         = false;
	}

	public function describe(): array {
		return array(
			'is_active'         => $this->is_active,
			'activate_time'     => $this->activate_time,
			'activate_reason'   => $this->activate_reason,
			'deactivate_time'   => $this->deactivate_time,
			'deactivate_reason' => $this->deactivate_reason,
		);
	}
}
