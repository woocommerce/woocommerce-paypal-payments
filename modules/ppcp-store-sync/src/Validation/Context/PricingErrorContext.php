<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

/**
 * Base context class for pricing-related validation issues.
 *
 * Concrete subclasses must set SPECIFIC_ISSUE to a ContextPricingIssue constant.
 */
abstract class PricingErrorContext extends IssueContext {
}
