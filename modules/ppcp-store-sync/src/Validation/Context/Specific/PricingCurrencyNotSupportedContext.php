<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context\Specific;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextPricingIssue;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\PricingErrorContext;

class PricingCurrencyNotSupportedContext extends PricingErrorContext {
	protected const SPECIFIC_ISSUE = ContextPricingIssue::CURRENCY_NOT_SUPPORTED;
}
