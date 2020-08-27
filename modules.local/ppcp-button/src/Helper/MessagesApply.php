<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Helper;

class MessagesApply {


	private $countries = array(
		'US',
	);

	public function forCountry(): bool {
		$region  = wc_get_base_location();
		$country = $region['country'];
		return in_array( $country, $this->countries, true );
	}
}
