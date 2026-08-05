<?php

/**
 * WooCommerce Gift Cards.
 */
class WC_GC_Gift_Card_Product {

	/**
	 * @param WC_Product $product
	 * @return bool
	 */
	public static function is_gift_card( $product ) {}
}

/**
 * WooCommerce Deposits.
 */
class WC_Deposits_Product_Manager {

	/**
	 * Are deposits enabled for a specific product.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function deposits_enabled( $product_id ) {}
}

/**
 * WooCommerce Product Add-Ons.
 */
class WC_Product_Addons_Helper {

	/**
	 * @param int  $post_id
	 * @param bool $prefix
	 * @param bool $inc_parent
	 * @param bool $inc_global
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_product_addons( $post_id, $prefix = false, $inc_parent = true, $inc_global = true ) {}
}
