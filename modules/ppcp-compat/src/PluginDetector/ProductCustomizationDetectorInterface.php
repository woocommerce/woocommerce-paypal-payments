<?php
/**
 * Interface for per-product plugin customization detectors.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PluginDetector
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\PluginDetector;

/**
 * Interface ProductCustomizationDetectorInterface
 */
interface ProductCustomizationDetectorInterface {

	/**
	 * @param \WC_Product $product The product to check.
	 * @return array<string, bool> List of plugins check was made for,
	 *      boolean shows whether the plugin has customized the product.
	 */
	public function scan( \WC_Product $product ): array;
}
