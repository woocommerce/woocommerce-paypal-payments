/**
 * Factory for v6 one-time payment sessions.
 *
 * @package
 */

import { approveOrder } from '../endpointsAdapter';
import {
	handleShippingAddressChange,
	handleShippingOptionsChange,
} from './shippingHandler';
import { refreshCartUi } from '../utils/cartUi';
import { handleError } from '../utils/errorHandler';
import { FundingSources } from '../utils/fundingSources';
import { WALLET_METHODS } from '../wallets/walletRegistry';

const SESSION_FACTORIES = {
	[ FundingSources.PAYPAL ]: 'createPayPalOneTimePaymentSession',
	[ FundingSources.VENMO ]: 'createVenmoOneTimePaymentSession',
	[ FundingSources.PAYLATER ]: 'createPayLaterOneTimePaymentSession',
	[ FundingSources.GOOGLEPAY ]: 'createGooglePayOneTimePaymentSession',
	[ FundingSources.APPLEPAY ]: 'createApplePayOneTimePaymentSession',
};

/**
 * The methods a session can be created for, and whose eligibility drives the
 * redraw check in boot.js.
 *
 * Derived from the factory table so a method can never be requested without a
 * factory to build it: calling a missing factory takes every button on the page
 * down.
 */
export const SUPPORTED_METHODS = Object.keys( SESSION_FACTORIES );

/**
 * Creates a one-time payment session for the given method.
 *
 * The defaults implement the classic-page flow. Surfaces with a different
 * completion flow (e.g. WooCommerce Blocks) supply overrides in `handlers`:
 * each key replaces the matching default, and provided shipping handlers
 * attach regardless of the classic shipping condition.
 *
 * @param {Object} sdkInstance - The PayPal SDK v6 instance.
 * @param {string} method      - The payment method, a SESSION_FACTORIES key.
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
		onCancel: handlers.onCancel || ( () => refreshCartUi( context ) ),

		onError:
			handlers.onError ||
			( ( error ) => {
				refreshCartUi( context );
				handleError( error );
			} ),
	};

	// Wallet sheets close before the order exists, so wallet sessions have no
	// onApprove: the wallet bridge drives create, confirm and approve itself.
	if ( ! WALLET_METHODS.includes( method ) ) {
		sessionConfig.onApprove =
			handlers.onApprove ||
			async function ( data ) {
				try {
					await approveOrder( config, context, method, data.orderId );
				} catch ( error ) {
					handleError( error );
				}
			};
	}

	// The default handlers post to the Store API directly, which desynchronises
	// the React cart UI — block surfaces must supply their own or go without.
	const isBlockContext =
		context === 'cart-block' || context === 'checkout-block';

	// Whether the PayPal popup should collect shipping details.
	// Only happens when the context requires shipping details and these details
	// cannot be entered on the current page.
	// - block checkout/checkout have a shipping form.
	// - pay-now can only pay an already finished order.
	const shouldHandleShipping =
		Boolean( config.shipping?.in_context?.[ context ] ) &&
		! isBlockContext &&
		! [ 'checkout', 'pay-now' ].includes( context ) &&
		method === FundingSources.PAYPAL;

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
