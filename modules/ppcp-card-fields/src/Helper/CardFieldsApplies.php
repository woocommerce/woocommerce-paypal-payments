<?php
/**
 * Service for checking whether Card Fields can be used in the current country.
 *
 * @package WooCommerce\PayPalCommerce\CardFields\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\CardFields\Helper;

/**
 * Class CardFieldsApplies
 */
class CardFieldsApplies {

	/**
	 * The matrix which countries can be used.
	 *
	 * @var array
	 */
	private $allowed_country_matrix;

	/**
	 * 2-letter country code of the shop.
	 *
	 * @var string
	 */
	private $country;

	/**
	 * CardFieldsApplies constructor.
	 *
	 * @param array  $allowed_country_matrix The matrix which countries can be used.
	 * @param string $country 2-letter country code of the shop.
	 */
	public function __construct(
		array $allowed_country_matrix,
		string $country
	) {
		$this->allowed_country_matrix = $allowed_country_matrix;
		$this->country                = $country;
	}

	/**
	 * Returns whether Card Fields can be used in the current country.
	 *
	 * @return bool
	 */
	public function for_country(): bool {
		return in_array( $this->country, $this->allowed_country_matrix, true );
	}
}
