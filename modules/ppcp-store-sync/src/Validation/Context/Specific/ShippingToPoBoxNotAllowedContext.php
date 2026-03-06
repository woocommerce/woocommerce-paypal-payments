<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context\Specific;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextShippingIssue;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\ShippingErrorContext;

class ShippingToPoBoxNotAllowedContext extends ShippingErrorContext {
	protected const SPECIFIC_ISSUE = ContextShippingIssue::SHIPPING_TO_PO_BOX_NOT_ALLOWED;
}
