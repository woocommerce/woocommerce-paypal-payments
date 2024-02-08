<?php
/**
 * Properties of the AXO module.
 *
 * @package WooCommerce\PayPalCommerce\Axo\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo\Helper;

/**
 * Class PropertiesDictionary
 */
class PropertiesDictionary {

	/**
	 * Returns the possible list of possible address widget options.
	 *
	 * @return array
	 */
	public static function address_widget_options(): array {
		return array(
			'render'     => __( 'Render address options.', 'woocommerce-paypal-payments' ),
			'use_widget' => __( 'Use address widget.', 'woocommerce-paypal-payments' ),
		);
	}

	/**
	 * Returns the possible list of possible address widget options.
	 *
	 * @return array
	 */
	public static function payment_widget_options(): array {
		return array(
			'render'     => __( 'Render payment options.', 'woocommerce-paypal-payments' ),
			'use_widget' => __( 'Use payment widget.', 'woocommerce-paypal-payments' ),
		);
	}

}
