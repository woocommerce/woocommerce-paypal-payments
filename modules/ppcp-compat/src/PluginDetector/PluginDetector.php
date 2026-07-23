<?php
/**
 * Detects third-party plugins relevant for compatibility checks.
 *
 * @see self::scan() for the plugins list.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PluginDetector
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Compat\PluginDetector;

class PluginDetector {

	public const SUBSCRIPTIONS        = 'woocommerce-subscriptions';
	public const GIFT_CARDS           = 'woocommerce-gift-cards';
	public const PRODUCT_BUNDLES      = 'woocommerce-product-bundles';
	public const PRODUCT_ADDONS       = 'woocommerce-product-addons';
	public const MIN_MAX_QUANTITIES   = 'woocommerce-min-max-quantities';
	public const COMPOSITE_PRODUCTS   = 'woocommerce-composite-products';
	public const SHIPPING_PER_PRODUCT = 'woocommerce-shipping-per-product';
	public const DEPOSITS             = 'woocommerce-deposits';

	/**
	 * @return array<string, bool> List of plugins check was made for,
	 *      boolean shows whether the plugin is active
	 */
	public function scan(): array {
		return array(
			self::SUBSCRIPTIONS        => $this->is_woocommerce_subscriptions_active(),
			self::GIFT_CARDS           => $this->is_woocommerce_gift_cards_active(),
			self::PRODUCT_BUNDLES      => $this->is_woocommerce_product_bundles_active(),
			self::PRODUCT_ADDONS       => $this->is_woocommerce_product_addons_active(),
			self::MIN_MAX_QUANTITIES   => $this->is_woocommerce_min_max_quantities_active(),
			self::COMPOSITE_PRODUCTS   => $this->is_woocommerce_composite_products_active(),
			self::SHIPPING_PER_PRODUCT => $this->is_woocommerce_shipping_per_product_active(),
			self::DEPOSITS             => $this->is_woocommerce_deposits_active(),
		);
	}

	private function is_woocommerce_subscriptions_active(): bool {
		return class_exists( \WC_Subscriptions::class );
	}

	private function is_woocommerce_gift_cards_active(): bool {
		return function_exists( 'WC_GC' );
	}

	private function is_woocommerce_product_bundles_active(): bool {
		return class_exists( \WC_Bundles::class );
	}

	private function is_woocommerce_product_addons_active(): bool {
		return class_exists( \WC_Product_Addons::class );
	}

	private function is_woocommerce_min_max_quantities_active(): bool {
		return class_exists( \WC_Min_Max_Quantities::class );
	}

	private function is_woocommerce_composite_products_active(): bool {
		return class_exists( \WC_Composite_Products::class );
	}

	private function is_woocommerce_shipping_per_product_active(): bool {
		return class_exists( \WC_Shipping_Per_Product_Init::class );
	}

	private function is_woocommerce_deposits_active(): bool {
		return defined( 'WC_DEPOSITS_VERSION' );
	}
}
