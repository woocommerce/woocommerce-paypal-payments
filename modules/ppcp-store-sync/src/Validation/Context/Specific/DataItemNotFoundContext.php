<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context\Specific;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextDataIssue;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\DataErrorContext;

class DataItemNotFoundContext extends DataErrorContext {
	protected const SPECIFIC_ISSUE = ContextDataIssue::ITEM_NOT_FOUND;
}
