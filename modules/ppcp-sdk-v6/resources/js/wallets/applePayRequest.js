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
 * The postal address is asked for only where the sheet prices shipping with it;
 * everywhere else the page already holds one. Apple never returns a billing email
 * or phone, hence postalAddress alone for billing — walletContacts.js backfills the
 * email from the shipping contact.
 *
 * Built from PayPal's Apple Pay config exactly as it arrives, never from
 * formatConfigForPaymentRequest(): that helper lowercases the merchant
 * capabilities, and ApplePayMerchantCapability is a case-sensitive enum, so
 * "supports3ds" makes the session constructor throw before any sheet appears.
 * v5 reads the raw config for the same three fields.
 *
 * @param {Object}  applePayConfig                 - PayPal's Apple Pay config, as
 *                                                   returned by session.config().
 * @param {Object}  transaction                    - What the shopper is about to pay.
 * @param {string}  transaction.countryCode        - The merchant's country.
 * @param {string}  transaction.currencyCode       - The shop currency.
 * @param {string}  transaction.total              - The total as a decimal string.
 * @param {string}  transaction.displayName        - The shop name, labelling the total.
 * @param {boolean} [transaction.requiresShipping] - Whether to collect shipping
 *                                                   in the sheet.
 * @return {Object} The ApplePayPaymentRequest.
 */
export function buildApplePayRequest(
	applePayConfig,
	{ countryCode, currencyCode, total, displayName, requiresShipping = false }
) {
	const request = {
		countryCode: applePayConfig.countryCode || countryCode,
		merchantCapabilities: applePayConfig.merchantCapabilities,
		supportedNetworks: applePayConfig.supportedNetworks,
		currencyCode,
		total: {
			label: displayName,
			type: 'final',
			amount: total,
		},
		requiredBillingContactFields: [ 'postalAddress' ],
		requiredShippingContactFields: requiresShipping
			? [ 'postalAddress', 'email', 'phone' ]
			: [ 'email', 'phone' ],
	};

	if ( requiresShipping ) {
		request.shippingType = 'shipping';

		// Deliberately empty: the first onshippingcontactselected fills it, once
		// there is an address to price against.
		request.shippingMethods = [];
	}

	return request;
}
