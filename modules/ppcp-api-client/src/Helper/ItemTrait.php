<?php
/**
 * PayPal item helper.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

trait ItemTrait {

	/**
	 * Cleanups the description and prepares it for sending to PayPal.
	 *
	 * @param string $description Item description.
	 * @return string
	 */
	protected function prepare_description( string $description ): string {
		$description = strip_shortcodes( wp_strip_all_tags( $description ) );
		return substr( $description, 0, 127 ) ?: '';
	}

	/**
	 * Prepares the sku for sending to PayPal.
	 *
	 * @param string $sku Item sku.
	 * @return string
	 */
	protected function prepare_sku( string $sku ): string {
		return substr( wp_strip_all_tags( $sku ), 0, 127 ) ?: '';
	}
}
