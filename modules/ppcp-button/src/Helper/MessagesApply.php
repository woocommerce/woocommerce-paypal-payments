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
	);

	/**
	 * Determines whether a credit messaging is enabled for the shops location country.
	 *
	 * @return bool
	 */
	public function for_country(): bool {
		$region  = wc_get_base_location();
		$country = $region['country'];
		return in_array( $country, $this->countries, true );
	}
}
