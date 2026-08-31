/**
 * Watches the WooCommerce Blocks cart total for Pay Later messaging.
 *
 * Block-only, so it is kept out of `messages/renderer.js`: importing it there
 * would put `@wordpress/data` in the `boot.js` graph and make every classic
 * product, cart and checkout page depend on `wp-data` for a store it never
 * reads.
 *
 * @package
 */

import { select, subscribe } from '@wordpress/data';

import { minorUnitsToDecimal } from '../utils/amount';

const CART_STORE_KEY = 'wc/store/cart';

/**
 * Reads the cart total as a decimal string.
 *
 * @return {string} The total, or '' when the store is unavailable.
 */
function readCartTotal() {
	const totals = select( CART_STORE_KEY )?.getCartTotals?.();
	if ( ! totals ) {
		return '';
	}

	return minorUnitsToDecimal( totals.total_price, totals.currency_minor_unit );
}

/**
 * Calls back whenever the block cart total changes.
 *
 * The store notifies on every mutation, most of which leave the total alone,
 * so the callback fires only on an actual change.
 *
 * @param {(total: string) => void} onChange - Called with the new total.
 * @return {Function} Unsubscribes the watcher.
 */
export function watchBlockCartTotal( onChange ) {
	let lastTotal = readCartTotal();

	return subscribe( () => {
		const total = readCartTotal();
		if ( ! total || total === lastTotal ) {
			return;
		}

		lastTotal = total;
		onChange( total );
	} );
}
