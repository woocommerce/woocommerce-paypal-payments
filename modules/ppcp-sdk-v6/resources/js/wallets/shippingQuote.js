/**
 * Normalises a shipping quote into the one shape every wallet adapter reads.
 *
 * @package
 */

// What Google sends as the option id before the shopper has picked one.
const GOOGLE_UNSELECTED = 'shipping_option_unselected';

/**
 * @typedef {Object} ShippingOption
 * @property {string} id    - The WC rate id, e.g. flat_rate:3.
 * @property {string} label - The method name, shopper-facing.
 * @property {string} cost  - The cost as a decimal string; each sheet formats it.
 */

/**
 * @typedef {Object} ShippingQuote
 * @property {string}           total         - What the sheet displays and the order charges.
 * @property {string}           shippingFee   - The shipping portion of the total.
 * @property {string}           subtotal      - Items before shipping and tax.
 * @property {string}           tax           - Total tax.
 * @property {string}           discount      - Total discount, non-negative.
 * @property {boolean}          needsShipping - Whether anything needs shipping at all.
 * @property {?string}          selectedId    - The rate WC currently has chosen.
 * @property {ShippingOption[]} options       - The rates the shopper may pick.
 */

/**
 * Normalises the wallet-shipping endpoint's response into a quote.
 *
 * Every figure is already a decimal string at the shop's precision, and the total
 * is the one the purchase unit will carry, so nothing is recomputed here.
 *
 * @param {?Object} data - The ppc-sdk-v6-wallet-shipping response.
 * @return {ShippingQuote} The normalised quote.
 */
export function quoteFromResponse( data ) {
	return {
		total: data?.total ?? '',
		shippingFee: data?.shipping_fee ?? '',
		subtotal: data?.subtotal ?? '',
		tax: data?.tax ?? '',
		discount: data?.discount ?? '',
		needsShipping: Boolean( data?.needs_shipping ),
		selectedId: data?.selected_rate_id || null,
		options: Array.isArray( data?.options ) ? data.options : [],
	};
}

/**
 * Resolves which option a sheet's selection refers to.
 *
 * The sheet may name a rate that no longer exists after an address change, or
 * none at all, so the request never carries an id the cart cannot honour.
 *
 * @param {ShippingQuote} quote         - The quote to resolve against.
 * @param {?string}       [requestedId] - What the sheet reported.
 * @return {?string} A usable rate id, or null when there is none.
 */
export function resolveOptionId( quote, requestedId ) {
	const options = quote?.options ?? [];

	if ( ! options.length ) {
		return null;
	}

	if ( requestedId && requestedId !== GOOGLE_UNSELECTED ) {
		const match = options.find( ( option ) => option.id === requestedId );

		if ( match ) {
			return match.id;
		}
	}

	if ( quote.selectedId ) {
		return quote.selectedId;
	}

	return options[ 0 ].id;
}
