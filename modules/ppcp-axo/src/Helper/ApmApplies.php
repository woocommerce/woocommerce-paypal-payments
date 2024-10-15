<?php
/**
 * ApmApplies helper.
 * Checks if AXO is available for a given country and currency.
 *
 * @package WooCommerce\PayPalCommerce\Axo\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter;

/**
 * Class ApmApplies
 */
class ApmApplies {

	/**
	 * The matrix which countries and currency combinations can be used for AXO.
	 *
	 * @var array
	 */
	private $allowed_country_currency_matrix;

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
	 * @param array          $allowed_country_currency_matrix The matrix which countries and currency combinations can be used for AXO.
	 * @param CurrencyGetter $currency The getter of the 3-letter currency code of the shop.
	 * @param string         $country 2-letter country code of the shop.
	 */
	public function __construct(
		array $allowed_country_currency_matrix,
		CurrencyGetter $currency,
		string $country
	) {
		$this->allowed_country_currency_matrix = $allowed_country_currency_matrix;
		$this->currency                        = $currency;
		$this->country                         = $country;
	}

	/**
	 * Returns whether AXO can be used in the current country and the current currency used.
	 *
	 * @return bool
	 */
	public function for_country_currency(): bool {
		if ( ! in_array( $this->country, array_keys( $this->allowed_country_currency_matrix ), true ) ) {
			return false;
		}
		return in_array( $this->currency->get(), $this->allowed_country_currency_matrix[ $this->country ], true );
	}
}
