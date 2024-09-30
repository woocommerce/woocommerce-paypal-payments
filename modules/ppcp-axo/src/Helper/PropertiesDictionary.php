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
	 * Returns the list of possible cardholder name options.
	 *
	 * @return array
	 */
	public static function cardholder_name_options(): array {
		return array(
			'yes' => __( 'Yes', 'woocommerce-paypal-payments' ),
			'no'  => __( 'No', 'woocommerce-paypal-payments' ),
		);
	}

}
