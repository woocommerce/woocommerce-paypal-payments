/**
 * Placement concerns for a wallet that is its own payment-method row.
 *
 * On classic checkout Google Pay is a gateway rather than an express button, so
 * its row starts hidden until the browser proves it can pay, and its button
 * takes the place of "Place order" while the row is selected. Kept out of the
 * wallet bridge so that file stays about the wallet.
 *
 * @package
 */

import {
	getCurrentPaymentMethod,
	ORDER_BUTTON_SELECTOR,
} from '@ppcp-button/Helper/CheckoutMethodState';
import { hasJQuery } from '../utils/api';

/**
 * The methods whose visibility is already being synced, so the DOM-replacing
 * checkout updates (which re-run the render) do not stack listeners.
 */
const synced = new Set();

/**
 * Reveals the payment-method row once the wallet is known to be usable.
 *
 * PHP prints the row hidden because eligibility is only knowable client-side;
 * this is the counterpart that undoes it.
 *
 * @param {string} methodId - The WC payment method id.
 */
export function revealGateway( methodId ) {
	document
		.querySelectorAll( `style[data-hide-gateway="${ methodId }"]` )
		.forEach( ( style ) => style.remove() );

	// Only an inline display:none needs undoing; anything else is already
	// visible now that the style element above is gone.
	const row = document.querySelector(
		`.wc_payment_method.payment_method_${ methodId }`
	);
	if ( row && row.style.display === 'none' ) {
		row.style.display = '';
	}
}

/**
 * Keeps the wallet button and "Place order" mutually exclusive.
 *
 * The buyer pays with whichever of the two is showing: the wallet sheet needs a
 * direct click on the wallet button, so "Place order" must not offer a second,
 * broken route to the same gateway.
 *
 * Elements are re-queried on every pass because a checkout update replaces the
 * whole order-review DOM, including both of them.
 *
 * @param {string} methodId        - The WC payment method id.
 * @param {string} wrapperSelector - Selector of the wallet button's container.
 */
export function syncGatewayVisibility( methodId, wrapperSelector ) {
	const update = () => {
		const isSelected = getCurrentPaymentMethod() === methodId;

		const wrapper = document.querySelector( wrapperSelector );
		if ( wrapper ) {
			wrapper.style.display = isSelected ? '' : 'none';
		}

		const placeOrder = document.querySelector( ORDER_BUTTON_SELECTOR );
		if ( placeOrder ) {
			placeOrder.style.display = isSelected ? 'none' : '';
		}
	};

	update();

	if ( synced.has( methodId ) || ! hasJQuery() ) {
		return;
	}
	synced.add( methodId );

	jQuery( document.body ).on(
		'payment_method_selected updated_checkout',
		update
	);
}
