<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context\Specific;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextDataIssue;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\DataErrorContext;

class DataInvalidPhoneFormatContext extends DataErrorContext {
	protected const SPECIFIC_ISSUE = ContextDataIssue::INVALID_PHONE_FORMAT;
}
