/**
 * Creates a PayPal one-time payment session.
 *
 * @package
 */

import { approveOrder } from './approveOrder';
import {
	handleShippingAddressChange,
	handleShippingOptionsChange,
} from './shippingHandler';
import { handleError, handleCancel } from '../utils/errorHandler';

/**
 * Creates a PayPal payment session.
 *
 * @param {Object} sdkInstance - The PayPal SDK v6 instance.
 * @param {Object} config      - The wc_ppcp_sdk_v6 config object.
 * @return {Object} The payment session.
 */
export function createPayPalSession( sdkInstance, config ) {
	const shouldHandleShipping =
		config.shipping?.handle_in_paypal &&
		( config.shipping?.need_shipping || config.page_context === 'product' );

	const sessionConfig = {
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
	};

	if ( shouldHandleShipping ) {
		sessionConfig.onShippingAddressChange = async ( data ) => {
			try {
				await handleShippingAddressChange( data, config );
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( 'Shipping address change error:', error );
			}
		};

		sessionConfig.onShippingOptionsChange = async ( data ) => {
			try {
				await handleShippingOptionsChange( data, config );
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( 'Shipping options change error:', error );
			}
		};
	}

	return sdkInstance.createPayPalOneTimePaymentSession( sessionConfig );
}
