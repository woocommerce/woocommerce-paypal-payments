/**
 * The post-approval order review surface (continuation mode).
 *
 * A *regular* block payment method, so WooCommerce renders its own Place Order
 * button. Mirrors v5's PayPalComponent continuation branch.
 *
 * @package
 */

import { createElement, useEffect, useRef } from '@wordpress/element';
import { prefillFromPayPalOrder } from './prefillAddresses';
import { FundingSources } from '../utils/fundingSources';

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

	// Once only: a re-render or cart update must not overwrite edits the buyer
	// has since made to the form.
	const prefilled = useRef( false );

	useEffect( () => {
		if ( prefilled.current || ! continuation?.order ) {
			return;
		}
		prefilled.current = true;

		prefillFromPayPalOrder( continuation.order, {
			needsShipping: Boolean( shippingData?.needsShipping ),
			reflectInUi: true,
		} ).catch( ( error ) => {
			// Non-fatal: the buyer can still fill the form by hand.
			// eslint-disable-next-line no-console
			console.error(
				'[ppcp-sdk-v6] continuation prefill failed',
				error
			);
		} );
	}, [ continuation, shippingData ] );

	useEffect(
		() =>
			onPaymentSetup( () => ( {
				type: responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						paypal_order_id: continuation?.order_id,
						funding_source: continuation?.funding_source ||
							FundingSources.PAYPAL,
					},
				},
			} ) ),
		[ onPaymentSetup, continuation, responseTypes ]
	);

	// The only way out of continuation mode: express buttons stay suppressed
	// until the session order is cleared. Server-rendered so the wording and
	// nonce stay owned by CancelView/CancelController.
	return createElement( 'div', {
		dangerouslySetInnerHTML: { __html: continuation?.cancel?.html || '' },
	} );
}
