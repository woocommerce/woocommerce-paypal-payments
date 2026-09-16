/**
 * Advanced Card Fields (ACDC), v6 Web SDK — classic checkout.
 *
 * Mounts number/expiry/CVV into the existing WC card-form inputs and runs a new
 * card through the v6 card session (3D Secure included) before letting the
 * native submit through to CreditCardGateway::process_payment(). The SDK has no
 * cardholder-name component, so that input stays a plain field forwarded to
 * create-order.
 *
 * Scope: a fresh card, one-time or the $0 free-trial variant that saves via a
 * setup token. A selected saved token is left to the native submit.
 *
 * @package
 */

import { mountField } from './mountField';
import Spinner from '@ppcp-button/Helper/Spinner';
import { loadSdkV6 } from '../sdkLoader';
import { createCardOrder, approveCardOrder } from '../endpointsAdapter';
import {
	createCardSetupToken,
	exchangeSetupToken,
} from '../sessions/freeTrialSave';
import { hasJQuery } from '../utils/api';
import { handleError } from '../utils/errorHandler';
import { isFreeTrialCart } from '../utils/freeTrial';
import {
	CARD_DECLINE_MESSAGE,
	CARD_SAVE_DECLINE_MESSAGE,
	userFacingError,
} from '../utils/cardDeclineMessages';

const FIELD_TYPES = [ 'number', 'expiry', 'cvv' ];

/**
 * Reads the billing address from the classic checkout form for the card
 * session submit. The v6 SDK uses it for AVS/3D Secure verification, so
 * omitting it can make an otherwise-valid card fail authentication.
 *
 * @return {Object|undefined} A { billingAddress } options object, or undefined
 *                            when no postal code is present (e.g. pay-for-order).
 */
function billingAddressForSubmit() {
	const postalCode = document
		.querySelector( '#billing_postcode' )
		?.value?.trim();
	if ( ! postalCode ) {
		return undefined;
	}

	const billingAddress = { postalCode };
	const countryCode = document
		.querySelector( '#billing_country' )
		?.value?.trim();
	if ( countryCode ) {
		billingAddress.countryCode = countryCode;
	}

	return { billingAddress };
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
 * Whether the buyer opted to save the card during purchase, read from
 * WooCommerce's native tokenization checkbox for the card gateway (the same
 * checkbox v5's CheckoutActionHandler reads). Absent when vaulting is off.
 *
 * @return {boolean} True when the "save payment method" checkbox is checked.
 */
function shouldSavePaymentMethod() {
	return !! document.querySelector(
		'#wc-ppcp-credit-card-gateway-new-payment-method'
	)?.checked;
}

/**
 * Force-checks and disables the "Save to account" checkbox on a subscription
 * cart, whose card must be vaulted for renewals. Mirrors v5's CardFieldsRenderer.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 */
function forceSaveForSubscription( config ) {
	if (
		! config.has_subscriptions ||
		! config.card_fields?.is_vaulting_enabled
	) {
		return;
	}

	const saveToAccount = document.querySelector(
		'#wc-ppcp-credit-card-gateway-new-payment-method'
	);
	if ( saveToAccount ) {
		saveToAccount.checked = true;
		saveToAccount.disabled = true;
	}
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
 * WC's checkout AJAX replaces the whole `#payment` box, inputs and
 * `#place_order` included, detaching whatever was mounted before. So `attach()`
 * re-queries the DOM and re-runs itself on `updated_checkout`.
 *
 * @param {Object}   config     - The wc_ppcp_sdk_v6 config object.
 * @param {Function} [getTotal] - Returns the live cart total as a decimal
 *                              string. Omit it to use the total as the page
 *                              rendered.
 */
export async function initCardFields( config, getTotal = () => undefined ) {
	if ( ! config.card_fields?.enabled ) {
		return;
	}

	const {
		fields,
		payment_method: paymentMethod,
		styles,
	} = config.card_fields;
	const spinner = hasJQuery() ? Spinner.fullPage() : null;

	// A $0 free-trial subscription card is saved via a setup token instead of an
	// order. Re-asked per use, since a coupon can zero the cart after mounting.
	function isFreeTrial() {
		return isFreeTrialCart( config, getTotal() );
	}

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
				const cardSession = isFreeTrial()
					? sdk.createCardFieldsSavePaymentSession()
					: sdk.createCardFieldsOneTimePaymentSession();

				for ( const fieldType of FIELD_TYPES ) {
					if ( inputs[ fieldType ] ) {
						mountField(
							cardSession,
							fieldType,
							inputs[ fieldType ],
							styles
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
			const submitOptions = billingAddressForSubmit();

			// Free trial: confirm a setup token and store it; the native submit
			// that follows lets the gateway place the $0 order against it.
			if ( isFreeTrial() ) {
				const setupTokenId = await createCardSetupToken( config );
				// The save session takes no billing-address option; it confirms
				// the setup token, running 3D Secure when required.
				const saveResult = await cardSession.submit( setupTokenId );

				if ( saveResult.state === 'canceled' ) {
					return;
				}
				if ( saveResult.state !== 'succeeded' ) {
					throw userFacingError( CARD_SAVE_DECLINE_MESSAGE );
				}

				await exchangeSetupToken( config, setupTokenId );

				submitting = true;
				event.target.click();
				return;
			}

			// The name has no v6 field component; read the plain WC input.
			const cardName = getInputs().name?.value?.trim() || '';
			const { orderId } = await createCardOrder(
				config,
				config.page_context || 'checkout',
				cardName,
                shouldSavePaymentMethod(),
			);
			const result = submitOptions
				? await cardSession.submit( orderId, submitOptions )
				: await cardSession.submit( orderId );

			// Buyer closed the 3DS challenge or the popup; let them retry.
			if ( result.state === 'canceled' ) {
				return;
			}

			if ( result.state !== 'succeeded' ) {
				throw userFacingError( CARD_DECLINE_MESSAGE );
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
	 * (Re-)binds to the current DOM: attaches the click interceptor to an
	 * unbound `#place_order`, and drops a card session mounted into inputs that
	 * are now detached so it is rebuilt against the fresh ones.
	 */
	function attach() {
		const inputs = getInputs();
		if ( ! inputs.number || ! inputs.expiry || ! inputs.cvv ) {
			return;
		}

		// Re-run on every DOM refresh: WC rebuilds the tokenization checkbox with
		// the rest of #payment, so a single up-front call would not survive.
		forceSaveForSubscription( config );

		const placeOrderButton = document.querySelector( '#place_order' );
		if (
			! placeOrderButton ||
			placeOrderButton === boundPlaceOrderButton
		) {
			return;
		}

		// Only once the DOM really was replaced: mountField() skips inputs a
		// previous mount hid, so an earlier drop leaves a session with no fields.
		cardSessionPromise = null;
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
				// The checkbox is only in the DOM once the card gateway's fields
				// show, which selecting the method is what does.
				forceSaveForSubscription( config );
			}
		} );
	}
}
