/**
 * Factory for the v6 "save PayPal without purchase" session (vaulting).
 *
 * Used on the My Account › Add Payment Method page. The buyer approves a
 * setup token which onApprove exchanges — server-side, via the existing
 * Vault v3 endpoints — for a stored payment token.
 *
 * @package
 */

import { postJson } from '../utils/api';
import { handleError } from '../utils/errorHandler';

/**
 * Navigation seam: window.location is not mockable under jsdom, so
 * redirects go through this indirection to stay unit-testable.
 */
export const navigation = {
	assign: ( url ) => window.location.assign( url ),
};

/**
 * Creates a PayPal save-payment session for the add-payment-method page.
 *
 * onApprove receives `data.vaultSetupToken`, which is exchanged for a WC
 * payment token through the existing `ppc-create-payment-token` endpoint;
 * on success the buyer is sent to the saved payment methods page.
 *
 * @param {Object} sdkInstance - The PayPal SDK v6 instance.
 * @param {Object} config      - The localized save-payment config object.
 * @return {Object} The save payment session.
 */
export function createSavePayPalSession( sdkInstance, config ) {
	return sdkInstance.createPayPalSavePaymentSession( {
		async onApprove( data ) {
			try {
				await postJson( config.ajax.create_payment_token, {
					vault_setup_token: data.vaultSetupToken,
				} );

				navigation.assign( config.payment_methods_page );
			} catch ( error ) {
				handleError( error );
			}
		},

		onCancel() {},

		onError( error ) {
			handleError( error );
		},
	} );
}
