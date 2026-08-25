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
import { handleError, handleWarning } from '../utils/errorHandler';
import { FundingSources } from '../utils/fundingSources';
import { WALLET_METHODS } from '../wallets/walletRegistry';

const SESSION_FACTORIES = {
	[ FundingSources.PAYPAL ]: 'createPayPalOneTimePaymentSession',
	[ FundingSources.VENMO ]: 'createVenmoOneTimePaymentSession',
	[ FundingSources.PAYLATER ]: 'createPayLaterOneTimePaymentSession',
	[ FundingSources.GOOGLEPAY ]: 'createGooglePayOneTimePaymentSession',
	[ FundingSources.APPLEPAY ]: 'createApplePayOneTimePaymentSession',
	[ FundingSources.CARD ]: 'createPayPalGuestOneTimePaymentSession',
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

// Wallet sessions are merchant-presented, so the SDK shows no popup and these
// callbacks never fire; wallets collect shipping in their own sheet instead.
const SHIPPING_POPUP_METHODS = [ FundingSources.PAYPAL ];

// Contexts where the buyer enters shipping on the page: checkout has the form,
// pay-now pays an order that is already addressed.
const CONTEXTS_WITH_PAGE_SHIPPING = [ 'checkout', 'pay-now' ];

// Blocks collect shipping through the Blocks data store instead
// (blocks/blocksShippingHandlers.js); the defaults below post to the Store API
// and would desynchronise the React cart UI.
const CONTEXTS_WITH_OWN_SHIPPING_HANDLERS = [ 'cart-block', 'checkout-block' ];

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
		// Undefined leaves every other method on approveOrder's default gateway.
		const paymentMethod =
			method === FundingSources.CARD
				? config.card_button?.payment_method
				: undefined;

		sessionConfig.onApprove =
			handlers.onApprove ||
			async function ( data ) {
				try {
					await approveOrder(
						config,
						context,
						method,
						data.orderId,
						{},
						paymentMethod
					);
				} catch ( error ) {
					handleError( error );
				}
			};
	}

	// Card-only: the other factories' tolerance for unknown option keys is
	// unverified, while the guest session documents both handlers.
	if ( method === FundingSources.CARD ) {
		sessionConfig.onWarn = handlers.onWarn || handleWarning;

		// Fires after onApprove, which has already submitted the checkout form.
		// Kept as a log so nothing here can start a second submission.
		sessionConfig.onComplete =
			handlers.onComplete ||
			function () {
				// eslint-disable-next-line no-console
				console.debug( '[PPCP SDK v6] guest card payment complete' );
			};
	}

	const collectsShipping =
		SHIPPING_POPUP_METHODS.includes( method ) &&
		! CONTEXTS_WITH_PAGE_SHIPPING.includes( context ) &&
		Boolean( config.shipping?.in_context?.[ context ] );

	const useDefaultHandlers =
		collectsShipping &&
		! CONTEXTS_WITH_OWN_SHIPPING_HANDLERS.includes( context );

	// Rejections must propagate so the SDK is informed of the failure.
	if ( handlers.onShippingAddressChange ) {
		sessionConfig.onShippingAddressChange =
			handlers.onShippingAddressChange;
	} else if ( useDefaultHandlers ) {
		sessionConfig.onShippingAddressChange = ( data ) =>
			handleShippingAddressChange( data, config );
	}

	if ( handlers.onShippingOptionsChange ) {
		sessionConfig.onShippingOptionsChange =
			handlers.onShippingOptionsChange;
	} else if ( useDefaultHandlers ) {
		sessionConfig.onShippingOptionsChange = ( data ) =>
			handleShippingOptionsChange( data, config );
	}

	return sdkInstance[ SESSION_FACTORIES[ method ] ]( sessionConfig );
}
