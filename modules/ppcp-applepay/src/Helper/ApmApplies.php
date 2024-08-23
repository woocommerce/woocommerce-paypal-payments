<?php
/**
 * ApmApplies helper.
 * Checks if ApplePay is available for a given country and currency.
 *
 * @package WooCommerce\PayPalCommerce\ApplePay\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay\Helper;

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
	 * 3-letter currency code of the shop.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * 2-letter country code of the shop.
	 *
	 * @var string
	 */
	private $country;

	/**
	 * DccApplies constructor.
	 *
	 * @param array  $allowed_countries The list of which countries can be used for ApplePay.
	 * @param array  $allowed_currencies The list of which currencies can be used for ApplePay.
	 * @param string $currency 3-letter currency code of the shop.
	 * @param string $country 2-letter country code of the shop.
	 */
	public function __construct(
		array $allowed_countries,
		array $allowed_currencies,
		string $currency,
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
		return in_array( $this->currency, $this->allowed_currencies, true );
	}

}
