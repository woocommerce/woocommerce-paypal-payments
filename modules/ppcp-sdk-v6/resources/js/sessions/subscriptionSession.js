/**
 * Factory for the v6 native PayPal Subscription session.
 *
 * In subscriptions_api mode the buyer subscribes to the product's PayPal plan
 * instead of paying an order. The subscription is created server-side
 * (ppc-create-subscription) and approved through the existing
 * ppc-approve-subscription endpoint, the same contract as the v5 buttons.
 *
 * @package
 */

import { approveSubscription } from '../endpointsAdapter';
import { refreshCartUi } from '../utils/cartUi';
import { handleError } from '../utils/errorHandler';
import { releasingSdkBusy } from '../tokenRefresh';

/**
 * Creates a PayPal subscription session for the given page context.
 *
 * The approval carries the subscription id; the checkout token it was approved
 * under is read from the session when the approval data lacks it, since the
 * approve endpoint stores that order for the gateway.
 *
 * The defaults implement the classic-page flow. Surfaces with a different
 * completion flow (WooCommerce Blocks) supply overrides in `handlers`; each
 * key replaces the matching default, and onApprove receives the subscription
 * id and the checkout token.
 *
 * @param {Object} sdkInstance - The PayPal SDK v6 instance.
 * @param {Object} config      - The wc_ppcp_sdk_v6 config object.
 * @param {string} context     - The page context.
 * @param {Object} [handlers]  - Optional session callback overrides.
 * @return {Object} The subscription payment session.
 */
export function createSubscriptionSession(
	sdkInstance,
	config,
	context,
	handlers = {}
) {
	const approve =
		handlers.onApprove ||
		( async ( subscriptionId, orderId ) => {
			try {
				await approveSubscription(
					config,
					context,
					subscriptionId,
					orderId
				);
			} catch ( error ) {
				handleError( error );
			}
		} );

	const session = sdkInstance.createPayPalSubscriptionPaymentSession(
		releasingSdkBusy( {
			onApprove: ( data ) =>
				approve( data.subscriptionId, data.orderId || session.orderId ),

			onCancel:
				handlers.onCancel ||
				( () => {
					refreshCartUi( context );
				} ),

			onError:
				handlers.onError ||
				( ( error ) => {
					refreshCartUi( context );
					handleError( error );
				} ),
		} )
	);

	return session;
}
