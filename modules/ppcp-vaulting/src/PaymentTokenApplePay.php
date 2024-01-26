<?php
/**
 * WooCommerce Payment token for ApplePay.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use WC_Payment_Token;

/**
 * Class PaymentTokenApplePay
 */
class PaymentTokenApplePay extends WC_Payment_Token {
	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected $type = 'ApplePay';

	/**
	 * Extra data.
	 *
	 * @var string[]
	 */
	protected $extra_data = array();
}
