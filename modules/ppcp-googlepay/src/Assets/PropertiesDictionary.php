<?php
/**
 * Properties of the GooglePay module.
 *
 * @package WooCommerce\PayPalCommerce\Button\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay\Assets;

/**
 * Class Button
 */
class PropertiesDictionary {

	/**
	 * Returns the possible list of button colors.
	 *
	 * @return array
	 */
	public static function button_colors(): array {
		return array(
			'white' => __( 'White', 'woocommerce-paypal-payments' ),
			'black' => __( 'Black', 'woocommerce-paypal-payments' ),
		);
	}

	/**
	 * Returns the possible list of button types.
	 *
	 * @return array
	 */
	public static function button_types(): array {
		return array(
			'book'      => __( 'Book', 'woocommerce-paypal-payments' ),
			'buy'       => __( 'Buy', 'woocommerce-paypal-payments' ),
			'checkout'  => __( 'Checkout', 'woocommerce-paypal-payments' ),
			'donate'    => __( 'Donate', 'woocommerce-paypal-payments' ),
			'order'     => __( 'Order', 'woocommerce-paypal-payments' ),
			'pay'       => __( 'Pay', 'woocommerce-paypal-payments' ),
			'plain'     => __( 'Plain', 'woocommerce-paypal-payments' ),
			'subscribe' => __( 'Book', 'woocommerce-paypal-payments' ),
		);
	}
}
