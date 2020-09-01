<?php
/**
 * Helper class to determine if credit messaging should be displayed.
 *
 * @package Inpsyde\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Helper;

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
