<?php
/**
 * WooCommerce Payment token for PayPal.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use WC_Payment_Token;

/**
 * Class PaymentTokenPayPal
 */
class PaymentTokenPayPal extends WC_Payment_Token {
	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected $type = 'PayPal';

	/**
	 * Extra data.
	 *
	 * @var string[]
	 */
	protected $extra_data = array(
		'email'          => '',
		'payment_source' => '',
	);

	/**
	 * Get PayPal account email.
	 *
	 * @return string PayPal account email.
	 */
	public function get_email() {
		return $this->get_meta( 'email' );
	}

	/**
	 * Set PayPal account email.
	 *
	 * @param string $email PayPal account email.
	 */
	public function set_email( $email ) {
		$this->add_meta_data( 'email', $email, true );
	}

	/**
	 * Get the payment source.
	 *
	 * @return string The payment source.
	 */
	public function get_payment_source() {
		return $this->get_meta( 'payment_source' );
	}

	/**
	 * Set the payment source.
	 *
	 * @param string $payment_source The payment source.
	 */
	public function set_payment_source( string $payment_source ) {
		$this->add_meta_data( 'payment_source', $payment_source, true );
	}
}
