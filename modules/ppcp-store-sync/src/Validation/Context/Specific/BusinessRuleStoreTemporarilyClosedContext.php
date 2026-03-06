<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context\Specific;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextBusinessRuleIssue;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\BusinessRuleErrorContext;

class BusinessRuleStoreTemporarilyClosedContext extends BusinessRuleErrorContext {
	protected const SPECIFIC_ISSUE = ContextBusinessRuleIssue::STORE_TEMPORARILY_CLOSED;
}
