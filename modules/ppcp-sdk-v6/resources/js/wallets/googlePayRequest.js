/**
 * Builders for the two request objects Google's PaymentsClient takes.
 *
 * Both live here because the wallet bridge needs them together: one to decide
 * whether to render a button at all, one to open the sheet.
 *
 * Pure: no DOM, no SDK, no network, so the request shape is unit-testable.
 *
 * @package
 */

/**
 * Fixed rather than read from the session config, which carries only the major
 * version while Google requires both.
 */
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
 * Shipping on requires the caller to also pass an onPaymentDataChanged
 * callback, or Google rejects the request. It does not restrict countries via
 * shippingAddressParameters yet. Shipping off: the address comes off the
 * payment token instead, see walletContacts.js.
 *
 * @param {Object}  sessionConfig                     - The v6 session config, from
 *                                                      formatConfigForPaymentRequest().
 * @param {Object}  transaction                       - What the buyer is about to pay.
 * @param {string}  transaction.countryCode           - The merchant country.
 * @param {string}  transaction.currencyCode          - The shop currency.
 * @param {string}  transaction.total                 - The total as a decimal string.
 * @param {boolean} [transaction.requiresShipping]    - Whether to collect shipping
 *                                                      in the sheet.
 * @return {Object} The loadPaymentData request.
 */
export function buildPaymentDataRequest(
	sessionConfig,
	{ countryCode, currencyCode, total, requiresShipping = false }
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

	// Omitted rather than sent empty: Google pairs callbackIntents with the
	// PaymentsClient's paymentDataCallbacks, and rejects loadPaymentData with
	// "paymentDataCallbacks must be set" whenever the key is present at all,
	// an empty array included. The shipping flags default to false.
	if ( requiresShipping ) {
		request.callbackIntents = [ 'SHIPPING_ADDRESS', 'SHIPPING_OPTION' ];
		request.shippingAddressRequired = true;
		request.shippingOptionRequired = true;
	}

	return request;
}
