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
	 * From https://developer.paypal.com/docs/reports/reference/paypal-supported-currencies/
	 *
	 * @var string[]
	 */
	private $supported_currencies = array(
		'AUD',
		'BRL',
		'CAD',
		'CNY',
		'CZK',
		'DKK',
		'EUR',
		'HKD',
		'HUF',
		'ILS',
		'JPY',
		'MYR',
		'MXN',
		'TWD',
		'NZD',
		'NOK',
		'PHP',
		'PLN',
		'GBP',
		'RUB',
		'SGD',
		'SEK',
		'CHF',
		'THB',
		'USD',
	);

	/**
	 * 3-letter currency code of the shop.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * CurrencySupport constructor.
	 *
	 * @param string $currency 3-letter currency code of the shop.
	 */
	public function __construct( string $currency ) {
		$this->currency = $currency;
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
