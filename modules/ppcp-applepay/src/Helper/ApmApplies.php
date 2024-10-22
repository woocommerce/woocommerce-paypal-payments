<?php
/**
 * ApmApplies helper.
 * Checks if ApplePay is available for a given country and currency.
 *
 * @package WooCommerce\PayPalCommerce\ApplePay\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter;

/**
 * Class ApmApplies
 */
class ApmApplies {

	/**
	 * The list of which countries can be used for ApplePay.
	 *
	 * @var array
	 */
	private $allowed_countries;

	/**
	 * The list of which currencies can be used for ApplePay.
	 *
	 * @var array
	 */
	private $allowed_currencies;

	/**
	 * The getter of the 3-letter currency code of the shop.
	 *
	 * @var CurrencyGetter
	 */
	private CurrencyGetter $currency;

	/**
	 * 2-letter country code of the shop.
	 *
	 * @var string
	 */
	private $country;

	/**
	 * DccApplies constructor.
	 *
	 * @param array          $allowed_countries The list of which countries can be used for ApplePay.
	 * @param array          $allowed_currencies The list of which currencies can be used for ApplePay.
	 * @param CurrencyGetter $currency The getter of the 3-letter currency code of the shop.
	 * @param string         $country 2-letter country code of the shop.
	 */
	public function __construct(
		array $allowed_countries,
		array $allowed_currencies,
		CurrencyGetter $currency,
		string $country
	) {
		$this->allowed_countries  = $allowed_countries;
		$this->allowed_currencies = $allowed_currencies;
		$this->currency           = $currency;
		$this->country            = $country;
	}

	/**
	 * Returns whether ApplePay can be used in the current country used.
	 *
	 * @return bool
	 */
	public function for_country(): bool {
		return in_array( $this->country, $this->allowed_countries, true );
	}

	/**
	 * Returns whether ApplePay can be used in the current currency used.
	 *
	 * @return bool
	 */
	public function for_currency(): bool {
		return in_array( $this->currency->get(), $this->allowed_currencies, true );
	}

}
