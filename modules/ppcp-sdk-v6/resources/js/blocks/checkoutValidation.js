/**
 * WooCommerce Blocks checkout validation state.
 *
 * @package
 */

/**
 * Whether the WooCommerce Blocks checkout form currently has validation errors.
 *
 * Fails open: an unreadable store lets payment proceed rather than blocking the
 * buyer on a missing dependency. Reached through `window`, since optional
 * chaining does not protect an undeclared root identifier.
 *
 * @return {boolean} True when the checkout form has validation errors.
 */
export function hasCheckoutValidationErrors() {
	const validationStore = window.wp?.data?.select?.( 'wc/store/validation' );

	return validationStore?.hasValidationErrors?.() || false;
}
