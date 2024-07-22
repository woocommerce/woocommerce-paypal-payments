<?php
/**
 * The Apple Pay Payment Gateway
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Applepay;

use WC_Payment_Gateway;

/**
 * Class ApplePayGateway
 */
class ApplePayGateway extends WC_Payment_Gateway {
	const ID = 'ppcp-applepay';

}
