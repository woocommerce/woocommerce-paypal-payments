/**
 * Builder for the ApplePayPaymentRequest that opens the payment sheet.
 *
 * @package
 */

// Part of the request contract: the fields below are the ones this version takes.
export const APPLE_PAY_VERSION = 4;

/**
 * Builds the ApplePayPaymentRequest.
 *
 * On classic checkout the shopper already typed an address into the WC form, so
 * the sheet does not ask for it a second time. Apple never returns a billing email
 * or phone, hence postalAddress alone for billing — walletContacts.js backfills the
 * email from the shipping contact.
 *
 * @param {Object}  sessionConfig                  - The v6 session config, as returned
 *                                                   by formatConfigForPaymentRequest().
 * @param {Object}  transaction                    - What the shopper is about to pay.
 * @param {string}  transaction.currencyCode       - The shop currency.
 * @param {string}  transaction.total              - The total as a decimal string.
 * @param {string}  transaction.displayName        - The shop name, labelling the total.
 * @param {string}  transaction.context            - The page context.
 * @param {boolean} [transaction.requiresShipping] - Whether to collect shipping
 *                                                   in the sheet.
 * @return {Object} The ApplePayPaymentRequest.
 */
export function buildApplePayRequest(
	sessionConfig,
	{ currencyCode, total, displayName, context, requiresShipping = false }
) {
	const request = {
		// The session config calls this one merchantCountry.
		countryCode: sessionConfig.merchantCountry,
		merchantCapabilities: sessionConfig.merchantCapabilities,
		supportedNetworks: sessionConfig.supportedNetworks,
		currencyCode,
		total: {
			label: displayName,
			type: 'final',
			amount: total,
		},
		requiredBillingContactFields: [ 'postalAddress' ],
		requiredShippingContactFields:
			'checkout' === context
				? [ 'email', 'phone' ]
				: [ 'postalAddress', 'email', 'phone' ],
	};

	if ( requiresShipping ) {
		request.shippingType = 'shipping';

		// Deliberately empty: the first onshippingcontactselected fills it, once
		// there is an address to price against.
		request.shippingMethods = [];
	}

	return request;
}
