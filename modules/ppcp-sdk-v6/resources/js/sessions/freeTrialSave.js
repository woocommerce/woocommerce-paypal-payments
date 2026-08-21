/**
 * Free-trial ($0) subscription checkout — the vault "save without purchase" flow.
 *
 * A subscription cart whose initial total is 0 must not create a $0 PayPal order.
 * Instead the buyer approves a Vault v3 setup token, which is exchanged
 * server-side for a stored payment token, and the checkout is then submitted so
 * the gateway places the $0 WC order via its zero-total short-circuit. The stored
 * token is what WooCommerce Subscriptions charges on the first real renewal.
 *
 * Shared by every checkout surface (classic, blocks, pay-for-order) so the
 * request/response contract with the save-payment endpoints lives in one place.
 *
 * @package
 */

import { postJson } from '../utils/api';
import { handleError } from '../utils/errorHandler';

/**
 * Requests a PayPal wallet setup token from the existing WC AJAX endpoint.
 *
 * The returned promise is handed to session.start() unawaited — awaiting it
 * first loses the transient user activation and breaks the popup. It resolves
 * to a { vaultSetupToken } object, mirroring the { orderId } shape the one-time
 * session's create function returns.
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
 * Logged-in buyers use the create-payment-token endpoint (which links the token
 * to the account and, for cards, stashes it in the session for the gateway);
 * guests use the guest endpoint, which stashes the PayPal token object in the
 * session for the gateway to consume on order placement.
 *
 * @param {Object} config          - The wc_ppcp_sdk_v6 config object.
 * @param {string} vaultSetupToken - The approved Vault v3 setup token id.
 * @param {string} [paymentMethod] - The WC gateway the token belongs to.
 * @return {Promise<void>} Resolves once the token has been stored.
 */
export async function exchangeSetupToken( config, vaultSetupToken, paymentMethod ) {
	if ( config.user?.is_logged ) {
		const body = {
			vault_setup_token: vaultSetupToken,
			is_free_trial_cart: config.is_free_trial_cart ? '1' : '',
		};
		if ( paymentMethod ) {
			body.payment_method = paymentMethod;
		}
		await postJson( config.ajax.create_payment_token, body );
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
