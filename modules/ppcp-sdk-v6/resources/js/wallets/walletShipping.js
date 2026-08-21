/**
 * Quoting shipping for an open wallet payment sheet.
 *
 * The sheet asks mid-payment, before any PayPal order exists, so it cannot use the
 * popup's handlers in sessions/shippingHandler.js: those patch an order that is not
 * there yet. Each selection is written to the real cart instead, and the Store API
 * answers cart routes with the full cart, so the write and the read are one request
 * and the sheet's total is what ppc-create-order will charge.
 *
 * Wallet-agnostic; the per-wallet files translate a quote into their own sheet shape.
 *
 * @package
 */

import {
	fetchCart,
	selectShippingRate,
	updateCustomerAddress,
} from '../endpointsAdapter';
import { quoteFromCart } from './shippingQuote';

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
	 * The rate is selected after the address, because which rates a destination
	 * offers is only known once the address is set.
	 *
	 * @param {Object}  selection          - What the sheet reported.
	 * @param {Object}  selection.address  - WC address fields.
	 * @param {?string} [selection.rateId] - The rate the sheet selected.
	 * @return {Promise<Object>} The quote for the newest selection.
	 */
	function quote( { address, rateId = null } ) {
		const request = ++latestRequest;

		// Swallowed, so one failed selection does not skip the next one.
		writeChain = writeChain.catch( () => {} ).then( async () => {
			// Superseded while queued; the newer request speaks for it.
			if ( request !== latestRequest ) {
				return;
			}

			// Writing an empty address would blank the one WooCommerce holds.
			let cart = address?.country
				? await updateCustomerAddress( config, address )
				: await fetchCart( config );

			if ( rateId ) {
				cart = await selectShippingRate( config, rateId );
			}

			lastQuote = quoteFromCart( cart );
		} );

		return writeChain.then( () => {
			if ( ! lastQuote ) {
				throw new Error( 'Shipping could not be priced.' );
			}

			return lastQuote;
		} );
	}

	return {
		quote,
		current: () => lastQuote,
	};
}
