<?php
/**
 * Properties of the Save Payment Methods module.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter;

/**
 * Class SavePaymentMethodsApplies
 */
class SavePaymentMethodsApplies {

	/**
	 * The matrix which countries and currency combinations can be used for Save Payment Methods.
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
	 * SavePaymentMethodsApplies constructor.
	 *
	 * @param array          $allowed_country_currency_matrix The matrix which countries and currency combinations can be used for Save Payment Methods.
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
	 * Returns whether Save Payment Methods can be used in the current country and the current currency used.
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
