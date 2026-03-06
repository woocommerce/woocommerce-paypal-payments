<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context\Specific;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextShippingIssue;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\ShippingErrorContext;

class ShippingMissingShippingAddressContext extends ShippingErrorContext {
	protected const SPECIFIC_ISSUE = ContextShippingIssue::MISSING_SHIPPING_ADDRESS;
}
