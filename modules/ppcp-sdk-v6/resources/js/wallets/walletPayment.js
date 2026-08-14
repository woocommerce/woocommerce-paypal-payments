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
 * Creates the PayPal order, confirms it with the wallet payload, approves it.
 *
 * @param {Object}   args               - The payment inputs.
 * @param {Object}   args.config        - The wc_ppcp_sdk_v6 config object.
 * @param {string}   args.context       - The page context.
 * @param {Object}   args.session       - The v6 wallet payment session.
 * @param {string}   args.fundingSource - The funding source, e.g. googlepay.
 * @param {Object[]} args.purchaseUnits - Units the caller already resolved.
 * @param {Object}   args.confirmData   - Wallet payload for confirmOrder(), the
 *                                        order id aside.
 * @param {Object}   args.contact       - The { payer, shippingAddress } to
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

	// Unconfirmed which vocabulary a wallet session answers in: v5 Google Pay
	// returned status APPROVED, the v6 card session state succeeded.
	switch ( result?.status ?? result?.state ) {
		case 'APPROVED':
		case 'succeeded':
			break;
		case 'PAYER_ACTION_REQUIRED':
			// No re-confirm afterwards, matching v5: the server-side approval
			// below is what actually validates the resulting order state.
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
