<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

/**
 * Base context class for inventory-related validation issues.
 *
 * Concrete subclasses must set SPECIFIC_ISSUE to a ContextInventoryIssue constant.
 */
abstract class InventoryIssueContext extends IssueContext {
}
