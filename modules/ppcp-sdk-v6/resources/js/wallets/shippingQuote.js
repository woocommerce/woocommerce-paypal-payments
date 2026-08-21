/**
 * Normalises the WC Store API cart into the one shipping shape every wallet
 * adapter reads.
 *
 * @package
 */

import { minorUnitsToDecimal } from '../utils/amount';

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

let warnedAboutPackages = false;

/**
 * Normalises a WC Store API cart into a quote.
 *
 * Store API amounts are minor-unit integers carrying their own exponent, so a
 * plain divide by 100 would break 3-decimal currencies.
 *
 * Only the first shipping package is read: neither pay sheet can express a
 * per-package choice; PayPal does not support this either.
 *
 * @param {?Object} cart - The Store API cart response.
 * @return {ShippingQuote} The normalised quote.
 */
export function quoteFromCart( cart ) {
	const totals = cart?.totals ?? {};
	const minorUnit = totals.currency_minor_unit;
	const toDecimal = ( value ) => minorUnitsToDecimal( value, minorUnit );

	const packages = Array.isArray( cart?.shipping_rates )
		? cart.shipping_rates
		: [];

	if ( packages.length > 1 && ! warnedAboutPackages ) {
		warnedAboutPackages = true;
		// eslint-disable-next-line no-console
		console.warn(
			'[PPCP SDK v6] the cart has more than one shipping package; the payment sheet shows the first only.'
		);
	}

	const rates = packages[ 0 ]?.shipping_rates ?? [];

	const options = rates.map( ( rate ) => ( {
		id: String( rate.rate_id ?? '' ),
		label: String( rate.name ?? '' ),
		// Each rate carries its own exponent, which need not match the cart's.
		cost: minorUnitsToDecimal( rate.price, rate.currency_minor_unit ),
	} ) );

	const selected = rates.find( ( rate ) => rate.selected );

	return {
		total: toDecimal( totals.total_price ),
		shippingFee: toDecimal( totals.total_shipping ),
		subtotal: toDecimal( totals.total_items ),
		tax: toDecimal( totals.total_tax ),
		discount: toDecimal( totals.total_discount ),
		needsShipping: Boolean( cart?.needs_shipping ),
		selectedId: selected ? String( selected.rate_id ) : null,
		options,
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
