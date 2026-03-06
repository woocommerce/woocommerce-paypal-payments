<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

/**
 * Base context class for data-related validation issues.
 *
 * Concrete subclasses must set SPECIFIC_ISSUE to a ContextDataIssue constant.
 */
abstract class DataErrorContext extends IssueContext {
}
