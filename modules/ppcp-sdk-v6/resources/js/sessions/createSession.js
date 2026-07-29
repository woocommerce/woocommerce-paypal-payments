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
 * The default handlers implement the classic-page flow (approve then
 * continuation, cart-UI refresh on cancel/error, fetch-based shipping).
 * Surfaces with a different completion flow (e.g. WooCommerce Blocks)
 * supply overrides in `handlers`: each provided key replaces the matching
 * default, and provided shipping handlers attach regardless of the
 * classic shipping condition.
 *
 * @param {Object} sdkInstance - The PayPal SDK v6 instance.
 * @param {string} method      - The payment method (paypal, venmo, paylater).
 * @param {Object} config      - The wc_ppcp_sdk_v6 config object.
 * @param {string} context     - The page context.
 * @param {Object} [handlers]  - Optional session callback overrides.
 * @return {Object} The payment session.
 */
export function createSession(
	sdkInstance,
	method,
	config,
	context,
	handlers = {}
) {
	const sessionConfig = {
		onApprove:
			handlers.onApprove ||
			async function ( data ) {
				try {
					await approveOrder( config, context, method, data.orderId );
				} catch ( error ) {
					handleError( error );
				}
			},

		onCancel: handlers.onCancel || ( () => refreshCartUi( context ) ),

		onError:
			handlers.onError ||
			( ( error ) => {
				refreshCartUi( context );
				handleError( error );
			} ),
	};

	// The default handlers post to the Store API directly, which desynchronises
	// the React cart UI — block surfaces must supply their own or go without.
	const isBlockContext =
		context === 'cart-block' || context === 'checkout-block';

	const shouldHandleShipping =
		method === 'paypal' &&
		! isBlockContext &&
		config.shipping?.handle_in_paypal &&
		( config.shipping?.need_shipping || context === 'product' );

	// Rejections must propagate so the SDK is informed of the failure.
	if ( handlers.onShippingAddressChange ) {
		sessionConfig.onShippingAddressChange =
			handlers.onShippingAddressChange;
	} else if ( shouldHandleShipping ) {
		sessionConfig.onShippingAddressChange = ( data ) =>
			handleShippingAddressChange( data, config );
	}

	if ( handlers.onShippingOptionsChange ) {
		sessionConfig.onShippingOptionsChange =
			handlers.onShippingOptionsChange;
	} else if ( shouldHandleShipping ) {
		sessionConfig.onShippingOptionsChange = ( data ) =>
			handleShippingOptionsChange( data, config );
	}

	return sdkInstance[ SESSION_FACTORIES[ method ] ]( sessionConfig );
}
