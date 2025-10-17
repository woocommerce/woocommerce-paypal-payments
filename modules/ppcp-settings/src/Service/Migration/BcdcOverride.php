<?php
/**
 * The BCDC override flag.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

class BcdcOverride {
	public function is_active(): bool {
		return false;
	}
}
