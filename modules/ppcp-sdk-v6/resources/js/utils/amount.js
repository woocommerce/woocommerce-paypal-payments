/**
 * Amount conversion shared by the block cart/checkout entries.
 *
 * @package
 */

/**
 * Converts an amount in minor units to a decimal string.
 *
 * WooCommerce Blocks reports cart totals in minor units with the currency's
 * exponent alongside, in two different prop shapes (canMakePayment's
 * cartTotals and the billing prop); both funnel through here.
 *
 * @param {string|number} value       - The amount in minor units (e.g. '1050').
 * @param {number}        [minorUnit] - The currency exponent; defaults to 2.
 * @return {string} The decimal string, or '' when the value is not numeric.
 */
export function minorUnitsToDecimal( value, minorUnit ) {
	const minor = parseInt( value, 10 );
	if ( isNaN( minor ) ) {
		return '';
	}

	// parseInt so a numeric string exponent still works: 3-decimal currencies
	// (BHD, KWD, OMR, TND) would otherwise silently fall back to 2.
	const parsed = parseInt( minorUnit, 10 );
	const exponent = isNaN( parsed ) ? 2 : parsed;

	return ( minor / Math.pow( 10, exponent ) ).toFixed( exponent );
}

/**
 * Derives a decimal amount string from the WooCommerce Blocks billing prop.
 *
 * @param {Object} billing - The Blocks billing prop (cart total in minor units).
 * @return {string} The amount as a decimal string, or '' when unknown.
 */
export function amountFromBilling( billing ) {
	return minorUnitsToDecimal(
		billing?.cartTotal?.value,
		billing?.currency?.minorUnit
	);
}

/**
 * Derives a decimal amount string from cart totals, the shape used by
 * canMakePayment, the wc/store/cart store and the Store API cart response.
 *
 * @param {Object} cartTotals - The cart totals (amounts in minor units).
 * @return {string} The amount as a decimal string, or '' when unknown.
 */
export function amountFromCartTotals( cartTotals ) {
	return minorUnitsToDecimal(
		cartTotals?.total_price,
		cartTotals?.currency_minor_unit
	);
}
