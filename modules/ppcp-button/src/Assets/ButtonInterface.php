<?php
/**
 * The interface for the button asset renderer.
 *
 * @package WooCommerce\PayPalCommerce\Button\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Assets;

/**
 * Interface ButtonInterface
 */
interface ButtonInterface {

	/**
	 * Initializes the button.
	 */
	public function initialize(): void;

	/**
	 * Indicates if the button is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool;

	/**
	 * Renders the necessary HTML.
	 *
	 * @return bool
	 */
	public function render(): bool;

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
