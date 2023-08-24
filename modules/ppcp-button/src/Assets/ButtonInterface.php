<?php
/**
 * The interface for the button asset renderer.
 *
 * @package WooCommerce\PayPalCommerce\Button\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Assets;

/**
 * Interface SmartButtonInterface
 */
interface ButtonInterface {

	/**
	 * Renders the necessary HTML.
	 *
	 * @return bool
	 */
	public function render_buttons(): bool;

	/**
	 * Whether any of the scripts should be loaded.
	 */
	public function should_load_script(): bool;

	/**
	 * Enqueues scripts/styles.
	 */
	public function enqueue(): void;

	/**
	 * The configuration for the smart buttons.
	 *
	 * @return array
	 */
	public function script_data(): array;
}
