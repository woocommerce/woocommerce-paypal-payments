<?php
/**
 * The interface for the smart button asset renderer.
 *
 * @package WooCommerce\PayPalCommerce\Button\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Assets;

/**
 * Interface SmartButtonInterface
 */
interface SmartButtonInterface {

	/**
	 * Renders the necessary HTML.
	 *
	 * @return bool
	 */
	public function render_wrapper(): bool;

	/**
	 * Enqueues the necessary scripts.
	 *
	 * @return bool
	 */
	public function enqueue(): bool;

	/**
	 * Whether the running installation could save vault tokens or not.
	 *
	 * @return bool
	 */
	public function can_save_vault_token(): bool;
}
