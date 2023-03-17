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
	 * Whether the scripts should be loaded.
	 */
	public function should_load(): bool;

	/**
	 * Enqueues the necessary scripts.
	 */
	public function enqueue(): void;

	/**
	 * Whether the running installation could save vault tokens or not.
	 *
	 * @return bool
	 */
	public function can_save_vault_token(): bool;

	/**
	 * The configuration for the smart buttons.
	 *
	 * @return array
	 */
	public function script_data(): array;
}
