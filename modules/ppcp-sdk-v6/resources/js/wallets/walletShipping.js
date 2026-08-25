/**
 * Quoting shipping for an open wallet payment sheet.
 *
 * The sheet asks mid-payment, before any PayPal order exists, so it cannot use the
 * popup's handlers in sessions/shippingHandler.js: those patch an order that is not
 * there yet. Each selection goes to the real cart through ppc-sdk-v6-wallet-shipping,
 * which applies the address and the rate together and answers with the total the
 * purchase unit will carry, so the sheet shows what ppc-create-order charges.
 *
 * Wallet-agnostic; the per-wallet files translate a quote into their own sheet shape.
 *
 * @package
 */

import { quoteWalletShipping } from '../endpointsAdapter';
import { quoteFromResponse } from './shippingQuote';

/**
 * Whether the sheet in this context collects shipping.
 *
 * Decided per context by PHP. Where it is true the sheet's address becomes the
 * WC order's, because approveOrder() creates that order itself rather than
 * submitting the form the page may carry.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context.
 * @return {boolean} True when the sheet must ask for an address.
 */
export function walletShippingRequired( config, context ) {
	return Boolean( config.shipping?.in_context?.[ context ] );
}

/**
 * The countries the sheet may offer, or an empty list when unrestricted.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {string[]} ISO-2 country codes.
 */
export function walletShippingCountries( config ) {
	return config.shipping?.countries ?? [];
}

/**
 * Creates the shipping controller for one render of one wallet button.
 *
 * Every quote writes to the shopper's real cart, so rapid selections are chained
 * rather than merely raced, and a superseded one resolves against the newest answer.
 *
 * @param {Object} args        - The controller inputs.
 * @param {Object} args.config - The wc_ppcp_sdk_v6 config object.
 * @return {Object} The controller.
 */
export function createShippingController( { config } ) {
	let writeChain = Promise.resolve();
	let latestRequest = 0;

	// Also read back through current(): Apple must be handed a total even while it
	// is rejecting an address.
	let lastQuote = null;

	/**
	 * Prices a selection, keeping the result as the sheet's current answer.
	 *
	 * @param {Object}  selection                  - What the sheet reported.
	 * @param {Object}  selection.address          - WC shipping address fields.
	 * @param {?string} [selection.rateId]         - The rate the sheet selected.
	 * @param {?Object} [selection.billingAddress] - The card's billing address, at
	 *                                             commit time only.
	 * @param {?string} [selection.expectedTotal]  - The total to hold the server to,
	 *                                             at commit time only.
	 * @return {Promise<Object>} The quote for the newest selection.
	 */
	function quote( {
		address,
		rateId = null,
		billingAddress = null,
		expectedTotal = null,
	} ) {
		const request = ++latestRequest;

		// Swallowed, so one failed selection does not skip the next one.
		writeChain = writeChain.catch( () => {} ).then( async () => {
			// Superseded while queued; the newer request speaks for it.
			if ( request !== latestRequest ) {
				return;
			}

			lastQuote = quoteFromResponse(
				await quoteWalletShipping( config, {
					address,
					rateId,
					billingAddress,
					expectedTotal,
				} )
			);
		} );

		return writeChain.then( () => {
			if ( ! lastQuote ) {
				throw new Error( 'Shipping could not be priced.' );
			}

			return lastQuote;
		} );
	}

	/**
	 * Applies the authorized addresses and takes the server's verdict on the price.
	 *
	 * Authorization is the first point where the shipping street, the recipient and
	 * the card's billing address are known, so this is the first and last quote
	 * priced on exactly the basis the order will be created with.
	 *
	 * The total the sheet displayed goes with it, and the endpoint refuses a higher
	 * one: the shopper is charged the correct tax, and never more than they
	 * approved. Comparing there rather than here leaves nothing to bypass.
	 *
	 * @param {Object}  address          - Complete WC shipping address fields.
	 * @param {?Object} [billingAddress] - The card's WC billing address.
	 * @return {Promise<Object>} The committed quote.
	 * @throws {Error} When the endpoint refuses the new total.
	 */
	function commit( address, billingAddress = null ) {
		return quote( {
			address,
			rateId: lastQuote?.selectedId ?? null,
			// Mirrors WooCommerceOrderCreator::configure_addresses(), which bills
			// to the shipping address when the wallet reports none. Pricing has to
			// use whichever of the two the order will, not merely a plausible one.
			billingAddress: billingAddress?.country ? billingAddress : address,
			expectedTotal: lastQuote?.total ?? null,
		} );
	}

	return {
		quote,
		commit,
		current: () => lastQuote,
	};
}
