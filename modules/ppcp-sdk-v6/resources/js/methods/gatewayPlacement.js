/**
 * Placement concerns for a method that is its own payment-method row.
 *
 * On classic checkout Google Pay, Apple Pay and the Basic Card button are
 * gateways rather than express buttons, so each row starts hidden until it
 * proves it can pay, and its button takes the place of "Place order" while that
 * row is selected.
 *
 * Visibility is decided here for all of them at once rather than by each bridge
 * for itself, because the elements are shared: "Place order" and the express
 * wrapper belong to no single method, so two deciding independently would each
 * undo the other's answer.
 *
 * @package
 */

import {
	getCurrentPaymentMethod,
	isSavedPayPalTokenSelected,
	ORDER_BUTTON_SELECTOR,
	PaymentMethods,
} from '@ppcp-button/Helper/CheckoutMethodState';
import { hasJQuery } from '../utils/api';

/**
 * Wallet rows registered so far, as method id to button-container selector.
 *
 * Also the set that decides "Place order": it is the wallet rows that replace it,
 * so membership in this map is the question asked.
 */
const walletRows = new Map();

/**
 * The express buttons' container, which belongs to PayPal's own row.
 *
 * Held here because it is hidden for exactly the same reason the wallet rows are,
 * and only this module knows which row is selected.
 */
let expressRow = null;

/**
 * Whether the checkout events are already being listened to, so the
 * DOM-replacing updates (which re-run the render) do not stack listeners.
 */
let listening = false;

/**
 * Reveals the payment-method row once the wallet is known to be usable.
 *
 * PHP prints the row hidden because eligibility is only knowable client-side;
 * this is the counterpart that undoes it.
 *
 * @param {string} methodId - The WC payment method id.
 */
function revealGateway( methodId ) {
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
 * Shows or hides one element, leaving an absent one alone.
 *
 * @param {?string}  selector - The element's selector.
 * @param {boolean}  visible  - Whether it should be shown.
 */
function setVisible( selector, visible ) {
	if ( ! selector ) {
		return;
	}

	const element = document.querySelector( selector );
	if ( element ) {
		element.style.display = visible ? '' : 'none';
	}
}

/**
 * The button container belonging to a payment-method row, if this module owns one.
 *
 * @param {?string} methodId - The selected WC payment method id.
 * @return {?string} The container's selector, or null for any other row.
 */
function rowContainer( methodId ) {
	if ( walletRows.has( methodId ) ) {
		return walletRows.get( methodId );
	}

	// PayPal's express buttons stand in for "Place order" only for a NEW payment.
	// A saved PayPal token is completed through the vault component and "Place
	// order", so its row offers no express button and keeps "Place order".
	if ( PaymentMethods.PAYPAL === methodId ) {
		return isSavedPayPalTokenSelected() ? null : expressRow;
	}

	return null;
}

/**
 * Whether a container actually holds a button the buyer could press.
 *
 * Asked rather than assumed, because it is what makes hiding "Place order" safe:
 * a row whose button never rendered (an ineligible wallet, or the continuation
 * flow, where the approved order is completed through the form) keeps it, instead
 * of leaving the buyer with no way to pay at all.
 *
 * @param {?string} selector - The container's selector.
 * @return {boolean} False when the container is absent or empty.
 */
function hasRenderedButton( selector ) {
	const container = selector ? document.querySelector( selector ) : null;

	return !! container && container.childElementCount > 0;
}

/**
 * Shows the selected row's button and hides every other route to paying.
 *
 * The buyer pays with whichever control is showing: a wallet sheet needs a direct
 * click on its own button, so neither "Place order" nor another row's button may
 * offer a second, broken route to the same order.
 *
 * Elements are re-queried on every pass because a checkout update replaces the
 * whole order-review DOM, including all of them.
 */
function updateVisibility() {
	const selected = getCurrentPaymentMethod();

	for ( const [ methodId, selector ] of walletRows ) {
		setVisible( selector, methodId === selected );
	}

	// The express buttons pay for PayPal's row only; left showing, they offer a
	// PayPal payment while a wallet row is selected. A selected saved PayPal token
	// is paid through the vault component and "Place order", so the express buttons
	// hide for it too, the same as for any other non-PayPal row.
	setVisible(
		expressRow,
		PaymentMethods.PAYPAL === selected && ! isSavedPayPalTokenSelected()
	);

	// Answered once for all rows, not per wallet: each wallet asking only "am I
	// selected" meant the last one to run always won, so selecting the first
	// wallet left "Place order" showing next to its button.
	setVisible(
		ORDER_BUTTON_SELECTOR,
		! hasRenderedButton( rowContainer( selected ) )
	);
}

/**
 * Registers a wallet row and keeps the payment controls mutually exclusive.
 *
 * @param {Object}  args                  - The row being registered.
 * @param {string}  args.methodId         - The WC payment method id.
 * @param {string}  args.wrapperSelector  - Selector of the wallet button's container.
 * @param {?string} [args.expressSelector] - Selector of the express buttons'
 *                                           container, which PayPal's row owns.
 */
function syncGatewayVisibility( {
	methodId,
	wrapperSelector,
	expressSelector,
} ) {
	walletRows.set( methodId, wrapperSelector );

	if ( expressSelector ) {
		expressRow = expressSelector;
	}

	updateVisibility();

	if ( listening || ! hasJQuery() ) {
		return;
	}
	listening = true;

	jQuery( document.body ).on(
		'payment_method_selected updated_checkout',
		updateVisibility
	);

	// Switching between a saved PayPal token and "Use a new payment method" flips
	// whether the express buttons or "Place order" should show, without a DOM
	// rebuild, so re-run on that change too.
	jQuery( document ).on(
		'change',
		'input[name="wc-ppcp-gateway-payment-token"]',
		updateVisibility
	);
}

/**
 * Places a wallet that is its own payment-method row, if it is one.
 *
 * The reveal and the exclusivity sync are one step for the bridges: a revealed row
 * that nothing hid "Place order" for offers two routes to the same order. Does
 * nothing in the express contexts, where the wallet has no row of its own.
 *
 * @param {?Object} gateway - The { id, wrapper } of the wallet's row.
 * @param {Object}  config  - The wc_ppcp_sdk_v6 config object.
 */
export function revealMethodGateway( gateway, config ) {
	if ( ! gateway ) {
		return;
	}

	revealGateway( gateway.id );
	syncGatewayVisibility( {
		methodId: gateway.id,
		wrapperSelector: gateway.wrapper,
		expressSelector: config.wrapper,
	} );
}
