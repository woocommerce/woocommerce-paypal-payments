/**
 * Builders for the two request objects Google's PaymentsClient takes: one to
 * decide whether to render a button at all, one to open the sheet.
 *
 * @package
 */

// Fixed rather than read from the session config, which carries only the major
// version while Google requires both.
const API_VERSION = {
	apiVersion: 2,
	apiVersionMinor: 0,
};

/**
 * Builds the isReadyToPay request.
 *
 * @param {Object} sessionConfig - The v6 session config, as returned by
 *                                 formatConfigForPaymentRequest().
 * @return {Object} The isReadyToPay request.
 */
export function buildReadyToPayRequest( sessionConfig ) {
	return {
		...API_VERSION,
		allowedPaymentMethods: sessionConfig.allowedPaymentMethods,
	};
}

/**
 * Builds the loadPaymentData request that opens the payment sheet.
 *
 * With shipping on, the PaymentsClient must also carry an onPaymentDataChanged
 * callback or Google rejects the request. With it off, the address comes off the
 * payment token instead; see walletContacts.js.
 *
 * @param {Object}   sessionConfig                  - The v6 session config, from
 *                                                    formatConfigForPaymentRequest().
 * @param {Object}   transaction                    - What the buyer is about to pay.
 * @param {string}   transaction.countryCode        - The merchant country.
 * @param {string}   transaction.currencyCode       - The shop currency.
 * @param {string}   transaction.total              - The total as a decimal string.
 * @param {boolean}  [transaction.requiresShipping] - Whether to collect shipping
 *                                                    in the sheet.
 * @param {string[]} [transaction.countries]        - Shippable countries, when the
 *                                                    store restricts them.
 * @return {Object} The loadPaymentData request.
 */
export function buildPaymentDataRequest(
	sessionConfig,
	{
		countryCode,
		currencyCode,
		total,
		requiresShipping = false,
		countries = [],
	}
) {
	const request = {
		...API_VERSION,
		allowedPaymentMethods: sessionConfig.allowedPaymentMethods,
		merchantInfo: sessionConfig.merchantInfo,
		transactionInfo: {
			countryCode,
			currencyCode,
			totalPriceStatus: 'FINAL',
			totalPrice: total,
		},
		emailRequired: true,
	};

	// Omitted rather than sent empty: Google rejects loadPaymentData with
	// "paymentDataCallbacks must be set" whenever callbackIntents is present
	// at all, an empty array included.
	if ( requiresShipping ) {
		request.callbackIntents = [ 'SHIPPING_ADDRESS', 'SHIPPING_OPTION' ];
		request.shippingAddressRequired = true;
		request.shippingOptionRequired = true;
		request.shippingAddressParameters = {
			phoneNumberRequired: true,
		};

		// An exhaustive list would tell Google nothing.
		if ( countries.length ) {
			request.shippingAddressParameters.allowedCountryCodes = countries;
		}
	}

	return request;
}
