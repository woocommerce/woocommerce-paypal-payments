<?php
/**
 * Checks if the current installation uses supported currency.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

/**
 * Class CurrencySupport
 */
class CurrencySupport {

	/**
	 * Currencies supported by PayPal.
	 *
	 * @var string[]
	 */
	private $supported_currencies;

	/**
	 * 3-letter currency code of the shop.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * CurrencySupport constructor.
	 *
	 * @param string[] $supported_currencies Currencies supported by PayPal.
	 * @param string   $currency 3-letter currency code of the shop.
	 */
	public function __construct( array $supported_currencies, string $currency ) {
		$this->supported_currencies = $supported_currencies;
		$this->currency             = $currency;
	}

	/**
	 * Returns whether the currency is supported.
	 *
	 * @return bool
	 */
	public function supports_currency(): bool {
		return in_array( $this->currency, $this->supported_currencies, true );
	}
}
