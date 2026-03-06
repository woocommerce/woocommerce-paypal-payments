<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

/**
 * Base context class for shipping-related validation issues.
 *
 * Concrete subclasses must set SPECIFIC_ISSUE to a ContextShippingIssue constant.
 */
abstract class ShippingErrorContext extends IssueContext {
}
