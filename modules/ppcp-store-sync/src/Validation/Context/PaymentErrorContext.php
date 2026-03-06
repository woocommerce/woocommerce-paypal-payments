<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

/**
 * Base context class for payment-related validation issues.
 *
 * Concrete subclasses must set SPECIFIC_ISSUE to a ContextPaymentIssue constant.
 */
abstract class PaymentErrorContext extends IssueContext {
}
