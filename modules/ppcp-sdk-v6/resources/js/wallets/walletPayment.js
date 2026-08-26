/**
 * Drives the create, confirm and approve sequence for a wallet payment.
 *
 * The wallet-specific parts (confirmOrder payload, mapped contact) arrive as
 * arguments, so Apple Pay can reuse the sequence. DOM-free: failures throw
 * and the caller reports them.
 *
 * @package
 */

import { createOrder, approveOrder } from '../endpointsAdapter';

/**
 * Reads the outcome off a confirmOrder result.
 *
 * Wallet components disagree about both nesting and vocabulary: the Google session
 * unwraps its GraphQL payload down to the payment and reports `status: APPROVED`,
 * the Apple one leaves the mutation name on the payload, and the card session
 * reports `state: succeeded`. Absorbing all of that here keeps each wallet's bridge
 * free of the distinction.
 *
 * TODO (phase 3): if a third wallet brings a third shape, move this to the
 * wallet registry rather than growing the chain.
 *
 * @param {Object} result - What session.confirmOrder() resolved to.
 * @return {string|undefined} The status, however the wallet spells it.
 */
function confirmedStatus( result ) {
	const payment = result?.approveApplePayPayment ?? result;

	return payment?.status ?? payment?.state;
}

/**
 * Creates the PayPal order, confirms it with the wallet payload, approves it.
 *
 * @param {Object}   args                 - The payment inputs.
 * @param {Object}   args.config          - The wc_ppcp_sdk_v6 config object.
 * @param {string}   args.context         - The page context.
 * @param {Object}   args.session         - The v6 wallet payment session.
 * @param {string}   args.fundingSource   - The funding source, e.g. googlepay.
 * @param {Object[]} args.purchaseUnits   - Units the caller already resolved.
 * @param {Object}   args.confirmData     - Wallet payload for confirmOrder(), the
 *                                        order id aside.
 * @param {Object}   args.contact         - The { payer, shippingAddress } to
 *                                        record on the WC order.
 * @param {string}   [args.paymentMethod] - The WC gateway that processes the
 *                                        order; defaults to the express one.
 * @return {Promise<void>} Resolves once the order is approved.
 * @throws {Error} When the wallet does not approve the order.
 */
export async function payWithWallet( {
	config,
	context,
	session,
	fundingSource,
	purchaseUnits,
	confirmData,
	contact,
	paymentMethod,
} ) {
	// The units come from the caller because it already resolved them for the
	// sheet total; resolving again here would post ppc-change-cart twice.
	const { orderId } = await createOrder(
		config,
		context,
		fundingSource,
		purchaseUnits,
		paymentMethod
	);

	const result = await session.confirmOrder( { orderId, ...confirmData } );

	switch ( confirmedStatus( result ) ) {
		case 'APPROVED':
		case 'succeeded':
			break;
		case 'PAYER_ACTION_REQUIRED':
			// No re-confirm afterwards: the server-side approval below is what
			// validates the resulting order state.
			await session.initiatePayerAction( { orderId } );
			break;
		default:
			throw new Error( 'Wallet payment was not approved.' );
	}

	// On success this redirects or submits the checkout form.
	await approveOrder(
		config,
		context,
		fundingSource,
		orderId,
		contact,
		paymentMethod
	);
}
