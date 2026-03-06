<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

/**
 * Base context class for business-rule-related validation issues.
 *
 * Concrete subclasses must set SPECIFIC_ISSUE to a ContextBusinessRuleIssue constant.
 */
abstract class BusinessRuleErrorContext extends IssueContext {
}
