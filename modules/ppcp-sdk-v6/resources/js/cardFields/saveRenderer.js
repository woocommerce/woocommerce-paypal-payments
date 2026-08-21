/**
 * Advanced Card Fields — "save without purchase" (vaulting), v6 Web SDK.
 *
 * Used on the My Account › Add Payment Method page. Mounts the card fields
 * via a dedicated save session (which cannot coexist with a one-time card
 * session, so this page never creates one), and on submit creates a card
 * setup token, confirms it through the session (running 3D Secure when
 * required), and exchanges it server-side for a stored payment token.
 *
 * @package
 */

import { cardFieldStyles } from './cardFieldStyles';
import { hide } from '@ppcp-button/Helper/Hiding';
import { getCurrentPaymentMethod } from '@ppcp-button/Helper/CheckoutMethodState';
import { loadSdkV6 } from '../sdkLoader';
import { postJson } from '../utils/api';
import { handleError } from '../utils/errorHandler';
import { navigation } from '../utils/navigation';

const FIELD_TYPES = [ 'number', 'expiry', 'cvv', 'name' ];

/**
 * Mounts one v6 card field into its existing WC input's slot, hiding the
 * original (mirrors the classic renderer's mountField).
 *
 * @param {Object}      cardSession - The v6 card-fields save session.
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
 * Bootstraps the card "save for later" fields on the add-payment-method page.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6_save config object.
 */
export function initCardSaveFields( config ) {
	if ( ! config.card_fields?.enabled ) {
		return;
	}

	const { fields, payment_method: paymentMethod } = config.card_fields;
	let cardSessionPromise = null;
	let submitting = false;

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
				const cardSession = sdk.createCardFieldsSavePaymentSession();

				for ( const fieldType of FIELD_TYPES ) {
					if ( inputs[ fieldType ] ) {
						mountField( cardSession, fieldType, inputs[ fieldType ] );
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
	 * Requests a card setup token (with the store's 3DS/SCA contingency) from
	 * the existing WC AJAX endpoint.
	 *
	 * @return {Promise<string>} Resolves to the setup token id.
	 */
	async function createCardSetupToken() {
		const data = await postJson( config.ajax.create_setup_token, {
			payment_method: paymentMethod,
			verification_method: config.verification_method,
		} );
		return data.id;
	}

	function isCardGatewaySelected() {
		return getCurrentPaymentMethod() === paymentMethod;
	}

	/**
	 * Intercepts the native "Add payment method" submit for a card save.
	 *
	 * @param {Event} event - The click event.
	 */
	async function handleSubmit( event ) {
		if ( submitting || ! isCardGatewaySelected() ) {
			return;
		}

		event.preventDefault();
		event.stopImmediatePropagation();

		// Latch before the first await: a second click must not create a
		// second setup token and a second vaulted card. Stays true on the
		// success path because the page navigates away.
		submitting = true;

		try {
			const cardSession = await ensureCardSession();
			const setupTokenId = await createCardSetupToken();
			const result = await cardSession.submit( setupTokenId );

			// Buyer dismissed the 3DS challenge; let them retry.
			if ( result.state === 'canceled' ) {
				submitting = false;
				return;
			}
			if ( result.state !== 'succeeded' ) {
				throw new Error( 'Card could not be saved.' );
			}

			await postJson( config.ajax.create_payment_token, {
				vault_setup_token: setupTokenId,
			} );

			navigation.assign( config.payment_methods_page );
		} catch ( error ) {
			submitting = false;
			handleError( error );
		}
	}

	const placeOrderButton = document.querySelector( '#place_order' );
	placeOrderButton?.addEventListener( 'click', handleSubmit, true );

	// Mount the fields up front so styling and the session are ready before
	// the buyer submits.
	ensureCardSession().catch( handleError );
}
