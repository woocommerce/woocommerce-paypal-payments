/**
 * Creates a Pay Later one-time payment session.
 *
 * @package
 */

import { approveOrder } from './approveOrder';
import { handleError, handleCancel } from '../utils/errorHandler';

/**
 * Creates a Pay Later payment session.
 *
 * @param {Object} sdkInstance - The PayPal SDK v6 instance.
 * @param {Object} config      - The wc_ppcp_sdk_v6 config object.
 * @return {Object} The payment session.
 */
export function createPayLaterSession( sdkInstance, config ) {
	return sdkInstance.createPayLaterOneTimePaymentSession( {
		async onApprove( data ) {
			try {
				await approveOrder( config.ajax.approve_order, data.orderId );
			} catch ( error ) {
				handleError( error );
			}
		},

		onCancel() {
			handleCancel();
		},

		onError( error ) {
			handleError( error );
		},
	} );
}
