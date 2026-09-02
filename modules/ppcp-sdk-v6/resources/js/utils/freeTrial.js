/**
 * A subscription cart that totals zero cannot be purchased: PayPal rejects a $0
 * order, so the payment method is vaulted instead ("save without purchase") and
 * the gateway places the $0 WC order. Only PayPal can save without a purchase,
 * so such a cart offers nothing else.
 *
 * Two config flags feed this: `cart_needs_vaulting` covers the cart contents,
 * which a checkout cannot change, and the total is read live, because a coupon
 * can zero it after the page rendered.
 *
 * @package
 */

/**
 * Whether the cart must be vaulted rather than purchased.
 *
 * @param {Object}             config   - The wc_ppcp_sdk_v6 config object.
 * @param {string|number|null} [amount] - The live total, as a decimal string or
 *                                      number. Omit it to use the server's
 *                                      page-load answer.
 * @return {boolean} True when the save-without-purchase flow applies.
 */
export function isFreeTrialCart( config, amount ) {
	// Only a subscription can be vaulted; any other $0 cart needs no payment
	// method at all.
	if ( ! config?.cart_needs_vaulting ) {
		return false;
	}

	// Without a usable total, fall back to the server's page-load answer.
	const total = parseFloat( amount );
	if ( isNaN( total ) ) {
		return Boolean( config.is_free_trial_cart );
	}

	return total <= 0;
}
