<?php
/**
 * Helper class to determine if credit messaging should be displayed.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

/**
 * Class MessagesApply
 */
class MessagesApply {


	/**
	 * In which countries credit messaging is available.
	 *
	 * @var string[]
	 */
	private $allowed_countries;

	/**
	 * 2-letter country code of the shop.
	 *
	 * @var string
	 */
	private $country;

	/**
	 * MessagesApply constructor.
	 *
	 * @param string[] $allowed_countries In which countries credit messaging is available.
	 * @param string   $country 2-letter country code of the shop.
	 */
	public function __construct( array $allowed_countries, string $country ) {
		$this->allowed_countries = $allowed_countries;
		$this->country           = $country;
	}

	/**
	 * Determines whether a credit messaging is enabled for the shops location country.
	 *
	 * @return bool
	 */
	public function for_country(): bool {
		return in_array( $this->country, $this->allowed_countries, true );
	}
}
