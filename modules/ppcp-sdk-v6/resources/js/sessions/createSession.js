/**
 * Factory for v6 one-time payment sessions (PayPal, Venmo, Pay Later).
 *
 * @package
 */

import { approveOrder } from '../endpointsAdapter';
import {
	handleShippingAddressChange,
	handleShippingOptionsChange,
} from './shippingHandler';
import { hasJQuery } from '../utils/api';
import { handleError } from '../utils/errorHandler';

const SESSION_FACTORIES = {
	paypal: 'createPayPalOneTimePaymentSession',
	venmo: 'createVenmoOneTimePaymentSession',
	paylater: 'createPayLaterOneTimePaymentSession',
};

/**
 * Refreshes the cart UI after an abandoned or failed session.
 *
 * Mirrors the v5 product handler: the button click added the product to
 * the real cart, so the mini-cart fragments must reflect that even when
 * the buyer does not complete the purchase.
 *
 * @param {string} context - The page context.
 */
function refreshCartUi( context ) {
	if ( context === 'product' && hasJQuery() ) {
		jQuery( document.body ).trigger( 'wc_fragment_refresh' );
	}
}

/**
 * Creates a one-time payment session for the given method.
 *
 * @param {Object} sdkInstance - The PayPal SDK v6 instance.
 * @param {string} method      - The payment method (paypal, venmo, paylater).
 * @param {Object} config      - The wc_ppcp_sdk_v6 config object.
 * @param {string} context     - The page context.
 * @return {Object} The payment session.
 */
export function createSession( sdkInstance, method, config, context ) {
	const sessionConfig = {
		async onApprove( data ) {
			try {
				await approveOrder( config, context, method, data.orderId );
			} catch ( error ) {
				handleError( error );
			}
		},

		onCancel() {
			refreshCartUi( context );
		},

		onError( error ) {
			refreshCartUi( context );
			handleError( error );
		},
	};

	const shouldHandleShipping =
		method === 'paypal' &&
		config.shipping?.handle_in_paypal &&
		( config.shipping?.need_shipping || context === 'product' );

	if ( shouldHandleShipping ) {
		// Rejections must propagate so the SDK is informed of the failure.
		sessionConfig.onShippingAddressChange = ( data ) =>
			handleShippingAddressChange( data, config );
		sessionConfig.onShippingOptionsChange = ( data ) =>
			handleShippingOptionsChange( data, config );
	}

	return sdkInstance[ SESSION_FACTORIES[ method ] ]( sessionConfig );
}
