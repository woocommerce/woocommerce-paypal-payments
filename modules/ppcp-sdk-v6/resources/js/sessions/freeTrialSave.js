/**
 * Free-trial ($0) subscription checkout — the vault "save without purchase" flow.
 *
 * A subscription cart whose initial total is 0 must not create a $0 PayPal order.
 * Instead the buyer approves a Vault v3 setup token, exchanged server-side for
 * the stored token Subscriptions charges on the first renewal; the checkout is
 * then submitted so the gateway places the $0 WC order.
 *
 * Shared by every checkout surface, so the contract with the save-payment
 * endpoints lives in one place.
 *
 * @package
 */

import { postJson } from '../utils/api';
import { handleError } from '../utils/errorHandler';

/**
 * Requests a PayPal wallet setup token from the existing WC AJAX endpoint.
 *
 * The returned promise is handed to session.start() unawaited: awaiting it first
 * loses the transient user activation and breaks the popup.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {Promise<{vaultSetupToken: string}>} The setup token.
 */
export function createVaultSetupToken( config ) {
	return postJson( config.ajax.create_setup_token ).then( ( data ) => ( {
		vaultSetupToken: data.id,
	} ) );
}

/**
 * Requests a card setup token (with the store's 3DS/SCA contingency) from the
 * existing WC AJAX endpoint, for the card save session to confirm.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {Promise<string>} Resolves to the setup token id.
 */
export async function createCardSetupToken( config ) {
	const data = await postJson( config.ajax.create_setup_token, {
		payment_method: config.card_fields?.payment_method,
		verification_method: config.verification_method,
	} );
	return data.id;
}

/**
 * Exchanges an approved setup token for a stored WC payment token.
 *
 * Logged-in buyers get a token linked to their account; the guest endpoint
 * instead stashes the token in the session for the gateway to consume.
 *
 * @param {Object} config          - The wc_ppcp_sdk_v6 config object.
 * @param {string} vaultSetupToken - The approved Vault v3 setup token id.
 * @return {Promise<void>} Resolves once the token has been stored.
 */
export async function exchangeSetupToken( config, vaultSetupToken ) {
	if ( config.user?.is_logged ) {
		await postJson( config.ajax.create_payment_token, {
			vault_setup_token: vaultSetupToken,
		} );
		return;
	}

	await postJson( config.ajax.create_payment_token_for_guest, {
		vault_setup_token: vaultSetupToken,
	} );
}

/**
 * Creates a PayPal "save without purchase" session for a free-trial checkout.
 *
 * onApprove exchanges the setup token and then calls `onComplete`, which each
 * surface implements to submit its checkout (classic: `#place_order`; blocks:
 * the Blocks `onSubmit`).
 *
 * @param {Object}     sdkInstance        - The PayPal SDK v6 instance.
 * @param {Object}     config             - The wc_ppcp_sdk_v6 config object.
 * @param {Object}     handlers           - Surface callbacks.
 * @param {() => void} handlers.onComplete - Submits the checkout after the token is stored.
 * @param {(error: Error) => void} [handlers.onError] - Called on failure.
 * @return {Object} The PayPal save payment session.
 */
export function createFreeTrialPayPalSession(
	sdkInstance,
	config,
	{ onComplete, onError } = {}
) {
	return sdkInstance.createPayPalSavePaymentSession( {
		async onApprove( data ) {
			try {
				await exchangeSetupToken( config, data.vaultSetupToken );
				if ( onComplete ) {
					onComplete();
				}
			} catch ( error ) {
				if ( onError ) {
					onError( error );
				} else {
					handleError( error );
				}
			}
		},

		onCancel() {},

		onError( error ) {
			if ( onError ) {
				onError( error );
			} else {
				handleError( error );
			}
		},
	} );
}
