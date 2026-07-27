/**
 * Advanced Card Fields (ACDC), v6 Web SDK — classic checkout.
 *
 * Mounts the number/expiry/CVV/name fields into the existing WC card-form
 * inputs (the same DOM slots v5's paypal.CardFields() used), and wires the
 * checkout submission: submitting a new card runs through the v6 card
 * session (which also runs 3D Secure automatically), the result is
 * approved server-side, and the native checkout submission is then let
 * through to capture it via the existing CreditCardGateway::process_payment().
 *
 * Scope: fresh card, one-time payment only. Saving a new card, paying with
 * an already-saved card, and the $0 free-trial variant are untouched
 * here — this defers to the native submit whenever a saved token is
 * selected.
 *
 * @package
 */

import { cardFieldStyles } from './cardFieldStyles';
import { hide } from '@ppcp-button/Helper/Hiding';
import Spinner from '@ppcp-button/Helper/Spinner';
import { loadSdkV6 } from '../sdkLoader';
import { createCardOrder, approveCardOrder } from '../endpointsAdapter';
import { hasJQuery } from '../utils/api';
import { handleError } from '../utils/errorHandler';

const FIELD_TYPES = [ 'number', 'expiry', 'cvv', 'name' ];

/**
 * Mounts a single card field into its existing WC input's place, hiding
 * the original input (mirrors v5's Render.js placement/hiding).
 *
 * Unlike v5's paypal.CardFields(), which owns its container's layout via
 * its own .render() call, v6's createCardFieldsComponent() only returns a
 * plain HTMLElement — `style.input` styles what's inside it (font, color,
 * padding, ...), not the element's own box on this page. Theme rules
 * targeting the original `input` tag (e.g. `.input-wrapper input { height:
 * 100% }`) don't match this element, so its box has to be sized explicitly
 * from the original input's own rendered dimensions or it falls back to
 * the SDK's default (much taller than the form's inputs).
 *
 * @param {Object}      cardSession - The v6 card fields session.
 * @param {string}      fieldType   - number|expiry|cvv|name.
 * @param {HTMLElement} inputField  - The existing WC input to replace.
 */
function mountField( cardSession, fieldType, inputField ) {
	if ( ! inputField || inputField.hidden ) {
		return;
	}

	const computed = window.getComputedStyle( inputField );
	const options = {
		type: fieldType,
		style: { input: cardFieldStyles( inputField ) },
	};
	if ( inputField.getAttribute( 'placeholder' ) ) {
		options.placeholder = inputField.getAttribute( 'placeholder' );
	}

	const fieldElement = cardSession.createCardFieldsComponent( options );
	fieldElement.style.width = computed.width;
	fieldElement.style.height = computed.height;

	inputField.parentNode.appendChild( fieldElement );
	hide( inputField, true );
	inputField.hidden = true;
}

/**
 * Whether the buyer selected an existing saved payment token rather than
 * entering a new card — out of scope here, the native submit handles it.
 *
 * @return {boolean} True when a saved token (not "new") is selected.
 */
function isSavedTokenSelected() {
	const token = document.querySelector(
		'input[name="wc-ppcp-credit-card-gateway-payment-token"]:checked'
	)?.value;
	return !! token && token !== 'new';
}

/**
 * Whether the card gateway is the currently selected checkout payment method.
 *
 * @param {string} paymentMethod - The card gateway's payment method ID.
 * @return {boolean} True when the card gateway radio is checked.
 */
function isCardGatewaySelected( paymentMethod ) {
	return (
		document.querySelector( 'input[name="payment_method"]:checked' )
			?.value === paymentMethod
	);
}

/**
 * Bootstraps the ACDC card fields for the classic checkout page.
 *
 * WC's own checkout AJAX (`update_checkout`, fired on billing/shipping
 * field changes) replaces the whole `#payment` box — inputs and
 * `#place_order` included — with freshly rendered DOM nodes on every
 * call, detaching whatever this mounted/listened to before. So instead
 * of a one-shot setup, `attach()` re-queries the DOM and re-runs itself
 * on `updated_checkout`, the same event boot.js's renderAll() already
 * relies on to redraw the wallet buttons after that same DOM swap.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 */
export async function initCardFields( config ) {
	if ( ! config.card_fields?.enabled ) {
		return;
	}

	const { fields, payment_method: paymentMethod } = config.card_fields;
	const spinner = hasJQuery() ? Spinner.fullPage() : null;

	let cardSessionPromise = null;
	let submitting = false;
	let boundPlaceOrderButton = null;

	/**
	 * Re-queries the current card-form inputs, since a DOM swap invalidates
	 * any previously queried references.
	 *
	 * @return {Object} The number/expiry/cvv/name input elements.
	 */
	function getInputs() {
		return {
			number: document.querySelector( fields.number ),
			expiry: document.querySelector( fields.expiry ),
			cvv: document.querySelector( fields.cvv ),
			name: fields.name ? document.querySelector( fields.name ) : null,
		};
	}

	function ensureCardSession() {
		if ( ! cardSessionPromise ) {
			cardSessionPromise = ( async () => {
				const inputs = getInputs();
				const sdk = await loadSdkV6( config, 'checkout' );
				const cardSession = sdk.createCardFieldsOneTimePaymentSession();

				for ( const fieldType of FIELD_TYPES ) {
					if ( inputs[ fieldType ] ) {
						mountField(
							cardSession,
							fieldType,
							inputs[ fieldType ]
						);
					}
				}

				return cardSession;
			} )().catch( ( error ) => {
				cardSessionPromise = null;
				throw error;
			} );
		}
		return cardSessionPromise;
	}

	/**
	 * Intercepts the native "Place order" click for a new-card submission.
	 * Lets the click through untouched for a saved token, a different
	 * gateway, or the programmatic re-click issued after approval.
	 *
	 * @param {Event} event - The click event.
	 */
	async function handleSubmit( event ) {
		if (
			submitting ||
			! isCardGatewaySelected( paymentMethod ) ||
			isSavedTokenSelected()
		) {
			return;
		}

		event.preventDefault();
		event.stopImmediatePropagation();
		spinner?.block();

		try {
			const cardSession = await ensureCardSession();
			const { orderId } = await createCardOrder( config );
			const result = await cardSession.submit( orderId );

			// Buyer closed the 3DS challenge or the popup; let them retry.
			if ( result.state === 'canceled' ) {
				return;
			}

			if ( result.state !== 'succeeded' ) {
				throw new Error( 'Card payment failed.' );
			}

			await approveCardOrder( config, orderId );

			submitting = true;
			event.target.click();
		} catch ( error ) {
			handleError( error );
		} finally {
			submitting = false;
			spinner?.unblock();
		}
	}

	/**
	 * (Re-)binds to the current DOM: attaches the click interceptor to
	 * `#place_order` if it's a node we haven't bound yet, and drops any
	 * card session mounted into now-detached inputs so it gets rebuilt
	 * against the fresh ones.
	 */
	function attach() {
		const inputs = getInputs();
		if ( ! inputs.number || ! inputs.expiry || ! inputs.cvv ) {
			return;
		}

		cardSessionPromise = null;

		const placeOrderButton = document.querySelector( '#place_order' );
		if (
			! placeOrderButton ||
			placeOrderButton === boundPlaceOrderButton
		) {
			return;
		}
		boundPlaceOrderButton = placeOrderButton;
		placeOrderButton.addEventListener( 'click', handleSubmit, true );

		// Mount fields as soon as the card gateway is selected, so styling
		// and the session are ready before the buyer tries to submit.
		if ( isCardGatewaySelected( paymentMethod ) ) {
			ensureCardSession().catch( handleError );
		}
	}

	attach();

	if ( hasJQuery() ) {
		jQuery( document.body ).on( 'updated_checkout', attach );

		jQuery( document.body ).on( 'payment_method_selected', () => {
			if ( isCardGatewaySelected( paymentMethod ) ) {
				ensureCardSession().catch( handleError );
			}
		} );
	}
}
