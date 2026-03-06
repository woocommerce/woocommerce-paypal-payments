<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context\Specific;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextInventoryIssue;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\InventoryIssueContext;

class InventoryItemDiscontinuedContext extends InventoryIssueContext {
	protected const SPECIFIC_ISSUE = ContextInventoryIssue::ITEM_DISCONTINUED;
}
