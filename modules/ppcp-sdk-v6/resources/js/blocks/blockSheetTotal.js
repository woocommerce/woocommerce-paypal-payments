/**
 * The cart total for a wallet sheet on the block cart and checkout, or '' while
 * it is unknown.
 *
 * Read from the cart store, not from the `billing` prop: WooCommerce updates
 * `billing.cartTotal` in an effect after the render, so an express method gets
 * the new total only on the next render, which the block cart may never do.
 *
 * @package
 */

import { select, subscribe } from '@wordpress/data';

import { amountFromCartTotals } from '../utils/amount';

const CART_STORE_KEY = 'wc/store/cart';

/**
 * Whether a Store API request is changing the cart.
 *
 * Each flag clears in the same store update that carries the new total.
 *
 * @param {Object} cart - The cart store selectors.
 * @return {boolean} True while the request runs.
 */
function isCartUpdating( cart ) {
	return (
		cart.isApplyingCoupon() ||
		cart.isRemovingCoupon() ||
		cart.hasPendingItemsOperations() ||
		cart.isShippingRateBeingSelected() ||
		cart.isCustomerDataUpdating()
	);
}

/**
 * @return {string} The total, or '' while it is not known.
 */
function readSheetTotal() {
	const cart = select( CART_STORE_KEY );

	// Before the cart loads, the store holds default totals.
	if ( ! cart?.hasFinishedResolution( 'getCartData' ) ) {
		return '';
	}

	if ( isCartUpdating( cart ) ) {
		return '';
	}

	return amountFromCartTotals( cart.getCartTotals() );
}

export const blockSheetTotal = {
	get: readSheetTotal,

	/**
	 * @param {(total: string) => void} onChange - Called with each new total.
	 * @return {Function} Unsubscribes.
	 */
	subscribe( onChange ) {
		let lastTotal = readSheetTotal();

		// The store notifies on every mutation, most of which leave the total alone.
		return subscribe( () => {
			const total = readSheetTotal();

			if ( total !== lastTotal ) {
				lastTotal = total;
				onChange( total );
			}
		} );
	},
};
