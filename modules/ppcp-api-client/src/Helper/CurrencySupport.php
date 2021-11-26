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
	 * Returns whether the given currency is supported.
	 *
	 * @param string $currency 3-letter currency code.
	 * @return bool
	 */
	public function supports_currency( string $currency ): bool {
		return in_array( $currency, $this->supported_currencies, true );
	}

	/**
	 * Returns whether the current WC currency is supported.
	 *
	 * @return bool
	 */
	public function supports_wc_currency(): bool {
		return $this->supports_currency( get_woocommerce_currency() );
	}
}
