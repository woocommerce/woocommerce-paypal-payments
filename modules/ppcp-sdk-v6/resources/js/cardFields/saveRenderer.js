/**
 * Advanced Card Fields — "save without purchase" (vaulting), v6 Web SDK.
 *
 * Used on the My Account › Add Payment Method page and the subscription
 * change-payment-method page. Mounts the card fields
 * via a dedicated save session (which cannot coexist with a one-time card
 * session, so this page never creates one), and on submit creates a card
 * setup token, confirms it through the session (running 3D Secure when
 * required), and exchanges it server-side for a stored payment token.
 *
 * @package
 */

import { mountField } from './mountField';
import { getCurrentPaymentMethod } from '@ppcp-button/Helper/CheckoutMethodState';
import { loadSdkV6 } from '../sdkLoader';
import { postJson } from '../utils/api';
import { handleError } from '../utils/errorHandler';
import { completeSave } from '../sessions/completeSave';
import {
	CARD_SAVE_DECLINE_MESSAGE,
	userFacingError,
} from '../utils/cardDeclineMessages';

const FIELD_TYPES = [ 'number', 'expiry', 'cvv', 'name' ];

/**
 * Whether the buyer picked one of their saved cards (offered on the
 * change-payment page) rather than entering a new one, which the native
 * submit handles.
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
 * Force-checks and disables the "Save to account" checkbox on the
 * change-payment page: the card is saved regardless, since renewals charge it.
 */
function forceSaveToAccount() {
	const saveToAccount = document.querySelector(
		'#wc-ppcp-credit-card-gateway-new-payment-method'
	);
	if ( saveToAccount ) {
		saveToAccount.checked = true;
		saveToAccount.disabled = true;
	}
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

	const {
		fields,
		payment_method: paymentMethod,
		styles,
	} = config.card_fields;
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
		if (
			submitting ||
			! isCardGatewaySelected() ||
			isSavedTokenSelected()
		) {
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
				throw userFacingError( CARD_SAVE_DECLINE_MESSAGE );
			}

			const wcTokenId = await postJson(
				config.ajax.create_payment_token,
				{ vault_setup_token: setupTokenId }
			);

			await completeSave( config, wcTokenId, paymentMethod );
		} catch ( error ) {
			submitting = false;
			handleError( error );
		}
	}

	const placeOrderButton = document.querySelector( '#place_order' );
	placeOrderButton?.addEventListener( 'click', handleSubmit, true );

	if ( config.subscription_change ) {
		forceSaveToAccount();
	}

	// Mount the fields up front so styling and the session are ready before
	// the buyer submits.
	ensureCardSession().catch( handleError );
}
