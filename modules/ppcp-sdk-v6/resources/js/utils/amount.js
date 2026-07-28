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

	// Guards against a null/absent exponent, not just an undefined one.
	const exponent = Number.isInteger( minorUnit ) ? minorUnit : 2;

	return ( minor / Math.pow( 10, exponent ) ).toFixed( exponent );
}
