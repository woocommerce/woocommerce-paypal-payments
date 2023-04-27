<?php
/**
 * WooCommerce Payment token for PayPal ACDC (Advanced Credit and Debit Card).
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use WC_Payment_Token;

/**
 * Class PaymentTokenACDC
 */
class PaymentTokenACDC extends WC_Payment_Token {
	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected $type = 'ACDC';

	/**
	 * Stores Credit Card payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'last4'     => '',
		'card_type' => '',
	);

	/**
	 * Returns the last four digits.
	 *
	 * @param string $context The context.
	 * @return mixed|null
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @param string $last4 Last four digits.
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}

	/**
	 * Returns the card type (mastercard, visa, ...).
	 *
	 * @param string $context The context.
	 * @return string Card type
	 */
	public function get_card_type( $context = 'view' ) {
		return $this->get_prop( 'card_type', $context );
	}

	/**
	 * Set the card type (mastercard, visa, ...).
	 *
	 * @param string $type Credit card type (mastercard, visa, ...).
	 */
	public function set_card_type( $type ) {
		$this->set_prop( 'card_type', $type );
	}
}
