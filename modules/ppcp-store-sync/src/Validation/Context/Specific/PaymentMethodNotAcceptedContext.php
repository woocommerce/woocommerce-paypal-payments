<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context\Specific;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextPaymentIssue;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\PaymentErrorContext;

class PaymentMethodNotAcceptedContext extends PaymentErrorContext {
	protected const SPECIFIC_ISSUE = ContextPaymentIssue::PAYMENT_METHOD_NOT_ACCEPTED;
}
