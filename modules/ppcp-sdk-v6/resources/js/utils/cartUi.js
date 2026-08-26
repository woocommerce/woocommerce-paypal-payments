/**
 * Cart UI refreshes shared by the flows that mutate the cart before paying.
 *
 * @package
 */

import { hasJQuery } from './api';

/**
 * Refreshes the cart UI after an abandoned or failed session.
 *
 * The button click added the product to the real cart, so the mini-cart
 * fragments must reflect that even when the buyer does not complete.
 *
 * @param {string} context - The page context.
 */
export function refreshCartUi( context ) {
	if ( context === 'product' && hasJQuery() ) {
		jQuery( document.body ).trigger( 'wc_fragment_refresh' );
	}
}
