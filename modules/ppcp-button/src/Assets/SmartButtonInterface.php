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
	 * Whether any of our scripts (for DCC or product, mini-cart, non-block cart/checkout) should be loaded.
	 */
	public function should_load_ppcp_script(): bool;

	/**
	 * Enqueues our scripts/styles (for DCC and product, mini-cart and non-block cart/checkout)
	 */
	public function enqueue(): void;

	/**
	 * The configuration for the smart buttons.
	 *
	 * @return array
	 */
	public function script_data(): array;
}
