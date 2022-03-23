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
	 * @var array
	 */
	private $countries = array(
		'US',
		'DE',
		'GB',
		'FR',
		'AU',
		'IT',
		'ES',
	);

	/**
	 * 2-letter country code of the shop.
	 *
	 * @var string
	 */
	private $country;

	/**
	 * MessagesApply constructor.
	 *
	 * @param string $country 2-letter country code of the shop.
	 */
	public function __construct( string $country ) {
		$this->country = $country;
	}

	/**
	 * Determines whether a credit messaging is enabled for the shops location country.
	 *
	 * @return bool
	 */
	public function for_country(): bool {
		return in_array( $this->country, $this->countries, true );
	}
}
