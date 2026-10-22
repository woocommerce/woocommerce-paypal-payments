/**
 * Placement concerns for a method that is its own payment-method row.
 *
 * On classic checkout Google Pay, Apple Pay and the Basic Card button are
 * gateways rather than express buttons, so each row starts hidden until it
 * proves it can pay, and its button takes the place of "Place order" while that
 * row is selected. PayPal's own row does the same with the express buttons.
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
	PaymentContext,
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
 * Whether the express buttons' last render left their container empty, which
 * hands PayPal's row back to "Place order" (the gateway redirects to PayPal).
 */
let expressFailed = false;

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
 * Hidden with !important, so a theme's display rule cannot bring "Place order"
 * back next to the button that replaces it.
 *
 * @param {?string}  selector - The element's selector.
 * @param {boolean}  visible  - Whether it should be shown.
 */
function setVisible( selector, visible ) {
	if ( ! selector ) {
		return;
	}

	const element = document.querySelector( selector );
	if ( ! element ) {
		return;
	}

	if ( visible ) {
		element.style.removeProperty( 'display' );
	} else {
		element.style.setProperty( 'display', 'none', 'important' );
	}
}

/**
 * Whether PayPal's row is paid with the express buttons.
 *
 * Only for a NEW payment, since a saved PayPal token is completed through the
 * vault component and "Place order", and only until a render fails.
 *
 * @param {?string} methodId - The selected WC payment method id.
 * @return {boolean} False when the row is paid through "Place order".
 */
function paysWithExpressButtons( methodId ) {
	return (
		PaymentMethods.PAYPAL === methodId &&
		! isSavedPayPalTokenSelected() &&
		! expressFailed
	);
}

/**
 * Whether the selected row's own button takes the place of "Place order".
 *
 * @param {?string} methodId - The selected WC payment method id.
 * @return {boolean} False for a row that is paid through "Place order".
 */
function replacesPlaceOrder( methodId ) {
	if ( walletRows.has( methodId ) ) {
		return hasRenderedButton( walletRows.get( methodId ) );
	}

	// The container only has to exist, since the buttons are still loading into it
	// on the first pass; a failed render is reported by setExpressButtonsFailed().
	return (
		paysWithExpressButtons( methodId ) &&
		!! expressRow &&
		!! document.querySelector( expressRow )
	);
}

/**
 * Whether a container actually holds a button the buyer could press.
 *
 * Asked rather than assumed, because it is what makes hiding "Place order" safe:
 * a wallet whose button never rendered keeps it, instead of leaving the buyer
 * with no way to pay at all.
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
	// PayPal payment while another row is selected. After a failed render this
	// hides their empty container too.
	setVisible( expressRow, paysWithExpressButtons( selected ) );

	// Answered once for all rows, not per wallet: each wallet asking only "am I
	// selected" meant the last one to run always won, so selecting the first
	// wallet left "Place order" showing next to its button.
	setVisible( ORDER_BUTTON_SELECTOR, ! replacesPlaceOrder( selected ) );
}

/**
 * Keeps the decision current on the checkout events that change it.
 *
 * WooCommerce rebuilds "Place order" on updated_checkout, and the selection can
 * change without a rebuild.
 */
function listen() {
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
 * Lets the express buttons take the place of "Place order" on PayPal's row.
 *
 * Only where they render: in the continuation flow PayPal's row completes the
 * approved order through "Place order".
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 */
export function placeExpressButtons( config ) {
	if ( ! PaymentContext.Gateways.includes( config.page_context ) ) {
		return;
	}

	expressRow = config.wrapper;
	listen();
	updateVisibility();
}

/**
 * Records whether the express buttons' last render left their container empty.
 *
 * @param {boolean} failed - True when no button rendered.
 */
export function setExpressButtonsFailed( failed ) {
	expressFailed = failed;
	updateVisibility();
}

/**
 * Places a wallet that is its own payment-method row, if it is one.
 *
 * The reveal and the exclusivity sync are one step for the bridges: a revealed row
 * that nothing hid "Place order" for offers two routes to the same order. Does
 * nothing in the express contexts, where the wallet has no row of its own.
 *
 * @param {?Object} gateway - The { id, wrapper } of the wallet's row.
 */
export function revealMethodGateway( gateway ) {
	if ( ! gateway ) {
		return;
	}

	revealGateway( gateway.id );
	walletRows.set( gateway.id, gateway.wrapper );
	listen();
	updateVisibility();
}
