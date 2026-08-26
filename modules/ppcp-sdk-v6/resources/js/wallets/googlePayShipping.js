/**
 * Google Pay's in-sheet shipping callback.
 *
 * Translates between Google's paymentDataCallbacks protocol and the shared
 * shipping quote; everything about pricing lives in methodShipping.js.
 *
 * @package
 */

import { resolveOptionId } from './shippingQuote';
import { walletAddressToWc } from './walletContacts';

// Google rejects newShippingOptionParameters on a SHIPPING_OPTION trigger: the
// shopper is picking from the list it already has.
const OPTION_LIST_TRIGGERS = [ 'INITIALIZE', 'SHIPPING_ADDRESS' ];

/**
 * Formats a rate cost into the option's description.
 *
 * Google renders no price of its own beside a shipping option, so without this
 * the shopper cannot tell the options apart by cost.
 *
 * @param {string} cost         - The cost as a decimal string.
 * @param {string} currencyCode - The shop currency.
 * @return {string} The formatted cost.
 */
function formatCost( cost, currencyCode ) {
	const amount = parseFloat( cost );

	if ( isNaN( amount ) ) {
		return '';
	}

	try {
		return new Intl.NumberFormat( undefined, {
			style: 'currency',
			currency: currencyCode,
		} ).format( amount );
	} catch ( error ) {
		// An unknown currency code must not take the sheet down with it.
		return `${ cost } ${ currencyCode }`;
	}
}

/**
 * Maps a quote's options to Google's shape.
 *
 * Stripped to the three keys Google accepts: it throws on any other.
 *
 * @param {Object} quote        - The shipping quote.
 * @param {string} currencyCode - The shop currency.
 * @return {Object[]} Google shipping options.
 */
function googleOptions( quote, currencyCode ) {
	return quote.options.map( ( option ) => ( {
		id: option.id,
		label: option.label,
		description: formatCost( option.cost, currencyCode ),
	} ) );
}

/**
 * Builds the paymentDataCallbacks for a Google Pay sheet that collects shipping.
 *
 * Must be handed to the PaymentsClient constructor: Google does not accept
 * callbacks added to an existing client.
 *
 * @param {Object}   args              - The callback inputs.
 * @param {Object}   args.config       - The wc_ppcp_sdk_v6 config object.
 * @param {string}   args.currencyCode - The shop currency.
 * @param {string}   args.countryCode  - The merchant country.
 * @param {Object}   args.shipping     - The shared shipping controller.
 * @param {Function} [args.onQuote]    - Called with every fresh quote.
 * @return {Object} The paymentDataCallbacks object.
 */
export function buildPaymentDataCallbacks( {
	config,
	currencyCode,
	countryCode,
	shipping,
	onQuote,
} ) {
	return {
		onPaymentDataChanged: async ( paymentData ) => {
			const address = walletAddressToWc( paymentData.shippingAddress );

			let quote;

			try {
				// One request for both, so the options and the total describe the
				// same cart. Resolved against the previous quote, since the new
				// destination's options are not known yet; the endpoint answers
				// with the rate it actually applied.
				quote = await shipping.quote( {
					address,
					rateId: resolveOptionId(
						shipping.current(),
						paymentData.shippingOptionData?.id
					),
				} );
			} catch ( error ) {
				// Rethrown so Google keeps the sheet open on its own message;
				// a toast would be hidden behind the sheet anyway.
				// eslint-disable-next-line no-console
				console.error(
					'[PPCP SDK v6] Google Pay shipping update failed: ' +
						error.message
				);
				throw error;
			}

			onQuote?.( quote, address );

			if ( ! quote.options.length ) {
				return {
					error: {
						reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
						intent: 'SHIPPING_ADDRESS',
						message: config.labels?.shipping_unserviceable ?? '',
					},
				};
			}

			const update = {
				newTransactionInfo: {
					countryCode,
					currencyCode,
					totalPriceStatus: 'FINAL',
					totalPrice: quote.total,
				},
			};

			if (
				OPTION_LIST_TRIGGERS.includes( paymentData.callbackTrigger )
			) {
				update.newShippingOptionParameters = {
					defaultSelectedOptionId:
						quote.selectedId ?? quote.options[ 0 ].id,
					shippingOptions: googleOptions( quote, currencyCode ),
				};
			}

			return update;
		},
	};
}
