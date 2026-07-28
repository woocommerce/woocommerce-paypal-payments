/**
 * The post-approval order review surface (continuation mode).
 *
 * Rendered instead of the express buttons when the buyer has an approved
 * PayPal order in the WC session. Unlike the express component this is a
 * *regular* block payment method: WooCommerce renders its own Place Order
 * button, and this component only has to prefill the checkout from the PayPal
 * order, offer the cancel link, and hand the order id to the gateway.
 *
 * Mirrors v5's PayPalComponent continuation branch.
 *
 * @package
 */

import { createElement, useEffect, useRef } from '@wordpress/element';
import { paypalOrderToWcAddresses } from './address';

/**
 * @param {Object} props                    - Props from the Blocks registry.
 * @param {Object} props.config             - The localized sdk-v6 config.
 * @param {Object} props.eventRegistration  - Blocks checkout event subscriptions.
 * @param {Object} props.emitResponse       - Blocks response-type constants.
 * @param {Object} props.shippingData       - The Blocks shipping data.
 * @return {Object} The cancel-link element.
 */
export function V6ContinuationComponent( {
	config,
	eventRegistration,
	emitResponse,
	shippingData,
} ) {
	const { onPaymentSetup } = eventRegistration;
	const { responseTypes } = emitResponse;
	const continuation = config.continuation;

	// The prefill must run once. A re-render (or a cart update) must not
	// overwrite edits the buyer has since made to the checkout form.
	const prefilled = useRef( false );

	useEffect( () => {
		if ( prefilled.current || ! continuation?.order ) {
			return;
		}
		prefilled.current = true;

		const addresses = paypalOrderToWcAddresses( continuation.order );

		const cartStore = wp.data.dispatch( 'wc/store/cart' );

		// Persist server-side, then reflect in the UI. setShippingAddress is
		// skipped for carts that do not ship, matching v5.
		cartStore
			.updateCustomerData( {
				billing_address: addresses.billingAddress,
				shipping_address: addresses.shippingAddress,
			} )
			.then( () => {
				cartStore.setBillingAddress( addresses.billingAddress );
				if ( shippingData?.needsShipping ) {
					cartStore.setShippingAddress( addresses.shippingAddress );
				}
			} )
			.catch( ( error ) => {
				// Non-fatal: the buyer can still fill the form by hand.
				// eslint-disable-next-line no-console
				console.error( '[ppcp-sdk-v6] continuation prefill failed', error );
			} );
	}, [ continuation, shippingData ] );

	// Hand the already-approved order to the gateway on Place Order.
	useEffect(
		() =>
			onPaymentSetup( () => ( {
				type: responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						paypal_order_id: continuation?.order_id,
						funding_source: continuation?.funding_source || 'paypal',
					},
				},
			} ) ),
		[ onPaymentSetup, continuation, responseTypes ]
	);

	// The cancel link is the only way out of continuation mode while the
	// approved order lives in the session — express buttons stay suppressed
	// until it is cleared. Server-rendered so the wording and the nonce stay
	// owned by CancelView/CancelController.
	return createElement( 'div', {
		dangerouslySetInnerHTML: { __html: continuation?.cancel?.html || '' },
	} );
}
