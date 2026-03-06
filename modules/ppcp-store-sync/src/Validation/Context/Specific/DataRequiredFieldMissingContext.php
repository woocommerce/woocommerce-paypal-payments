<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context\Specific;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextDataIssue;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\DataErrorContext;

class DataRequiredFieldMissingContext extends DataErrorContext {
	protected const SPECIFIC_ISSUE = ContextDataIssue::REQUIRED_FIELD_MISSING;
}
