<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

/**
 * Base class for all validation issue contexts.
 *
 * Concrete context classes must extend one of the six category subclasses
 * and define the SPECIFIC_ISSUE constant using the matching Context*Issue enum.
 */
abstract class IssueContext {
	/**
	 * The specific issue code for this context.
	 *
	 * Concrete subclasses must override this constant using the appropriate
	 * Context*Issue enum value (e.g. ContextPricingIssue::PRICE_MISMATCH).
	 */
	protected const SPECIFIC_ISSUE = '';

	public function to_array(): array {
		return array(
			'specific_issue' => static::SPECIFIC_ISSUE,
		);
	}
}
