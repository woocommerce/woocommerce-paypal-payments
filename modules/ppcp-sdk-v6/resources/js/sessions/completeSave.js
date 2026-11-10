/**
 * Last step of the v6 "save without purchase" flows, shared by the PayPal and
 * card save sessions once the setup token became a WC payment token.
 *
 * On the subscription change-payment page the new token is assigned to the
 * subscription and the buyer returns to it; everywhere else the buyer lands
 * on the saved payment methods page.
 *
 * @package
 */

import { postJson } from '../utils/api';
import { navigation } from '../utils/navigation';

/**
 * @param {Object} config        - The wc_ppcp_sdk_v6_save config object.
 * @param {number} wcTokenId     - The WC payment token id from create_payment_token.
 * @param {string} paymentMethod - The gateway id the token belongs to.
 * @return {Promise<void>}
 */
export async function completeSave( config, wcTokenId, paymentMethod ) {
	const subscriptionChange = config.subscription_change;
	if ( ! subscriptionChange ) {
		navigation.assign( config.payment_methods_page );
		return;
	}

	// The endpoint would switch the gateway without attaching a token, leaving
	// renewals with nothing to charge.
	if ( ! wcTokenId ) {
		throw new Error( 'No payment token to assign to the subscription.' );
	}

	await postJson( subscriptionChange.ajax, {
		subscription_id: subscriptionChange.subscription_id,
		payment_method: paymentMethod,
		wc_payment_token_id: wcTokenId,
	} );

	navigation.assign( subscriptionChange.redirect_url );
}
