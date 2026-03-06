<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context\Specific;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextBusinessRuleIssue;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\BusinessRuleErrorContext;

class BusinessRuleAgeRestrictedProductContext extends BusinessRuleErrorContext {
	protected const SPECIFIC_ISSUE = ContextBusinessRuleIssue::AGE_RESTRICTED_PRODUCT;
}
