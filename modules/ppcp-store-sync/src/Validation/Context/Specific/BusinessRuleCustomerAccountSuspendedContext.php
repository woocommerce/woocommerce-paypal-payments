<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context\Specific;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextBusinessRuleIssue;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\BusinessRuleErrorContext;

class BusinessRuleCustomerAccountSuspendedContext extends BusinessRuleErrorContext {
	protected const SPECIFIC_ISSUE = ContextBusinessRuleIssue::CUSTOMER_ACCOUNT_SUSPENDED;
}
