<?php
/**
 * If we can't render our buttons, this Null object will be used.
 *
 * @package WooCommerce\PayPalCommerce\Button\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Assets;

/**
 * Class DisabledSmartButton
 */
class DisabledSmartButton implements SmartButtonInterface {

	/**
	 * Renders the necessary HTML.
	 *
	 * @return bool
	 */
	public function render_wrapper(): bool {
		return true;
	}

	/**
	 * Enqueues necessary scripts.
	 *
	 * @return bool
	 */
	public function enqueue(): bool {
		return true;
	}

	/**
	 * Whether tokens can be stored or not.
	 *
	 * @return bool
	 */
	public function can_save_vault_token(): bool {

		return false;
	}
}
