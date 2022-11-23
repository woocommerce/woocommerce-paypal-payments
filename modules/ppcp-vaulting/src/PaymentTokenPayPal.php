<?php
/**
 * WooCommerce Payment token for PayPal.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use WC_Payment_Token;

class PaymentTokenPayPal extends WC_Payment_Token {
	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected $type = 'PayPal';
}
