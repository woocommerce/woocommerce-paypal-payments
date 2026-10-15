/**
 * The block cart (e.g. the Mini-Cart drawer) fires no jQuery cart events.
 *
 * @package
 */

const CART_STORE = 'wc/store/cart';

/**
 * Calls back when the wc/store/cart total changes. The store holds default
 * totals until the cart loads, so the first loaded total is the baseline.
 *
 * @param {Function} callback - Called on each change.
 */
export function onBlockCartTotalChange( callback ) {
	const data = window.wp?.data;
	if ( ! data ) {
		return;
	}

	let lastTotal = null;
	data.subscribe( () => {
		const cart = data.select( CART_STORE );
		if ( ! cart?.hasFinishedResolution( 'getCartData' ) ) {
			return;
		}

		const total = cart.getCartTotals()?.total_price;
		if ( lastTotal !== null && total !== lastTotal ) {
			callback();
		}
		lastTotal = total;
	} );
}
