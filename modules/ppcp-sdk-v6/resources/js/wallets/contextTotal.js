/**
 * Resolves the amount a wallet sheet must display.
 *
 * Wallet-agnostic, so a second wallet can reuse it. DOM-free: the product form
 * lives behind endpointsAdapter.
 *
 * @package
 */

import { changeCart, fetchCartTotal } from '../endpointsAdapter';

/**
 * Resolves the sheet total and the purchase units to create the order with.
 *
 * The total must match what createOrder() charges, so both come from the same
 * source and purchaseUnits go straight to createOrder(). The total is never
 * re-rounded: forcing two decimals would understate 3-decimal currencies
 * (BHD, KWD, OMR, TND).
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context.
 * @return {Promise<{total: string, purchaseUnits: Object[]}>} Total and units.
 */
export async function resolveContextTotal( config, context ) {
	if ( context === 'product' ) {
		// The viewed product is not in the cart yet, so the cart total cannot
		// answer this.
		const purchaseUnits = await changeCart( config );

		return {
			total: purchaseUnits[ 0 ]?.amount?.value ?? '',
			purchaseUnits,
		};
	}

	if ( context === 'pay-now' ) {
		// An existing order is being paid, priced server-side from the order itself.
		// The cart holds an unrelated basket, so asking it would show a price this
		// page cannot charge.
		return {
			total: config.amount,
			purchaseUnits: [],
		};
	}

	return {
		// fetchCartTotal returns '' when the Store API call fails; the
		// localized amount is the page's own total.
		total: ( await fetchCartTotal( config ) ) || config.amount,
		purchaseUnits: [],
	};
}
