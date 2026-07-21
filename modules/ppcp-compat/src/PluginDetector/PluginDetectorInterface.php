<?php
/**
 * Interface for plugin detectors.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PluginDetector
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\PluginDetector;

/**
 * Interface PluginDetectorInterface
 */
interface PluginDetectorInterface {

	/**
	 * @return array<string, bool> List of plugins check was made for,
	 *      boolean shows whether the plugin is active
	 */
	public function scan(): array;
}
