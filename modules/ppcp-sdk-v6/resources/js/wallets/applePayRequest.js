/**
 * Builder for the ApplePayPaymentRequest that opens the payment sheet.
 *
 * Pure: no DOM, no SDK, no network, so the request shape is unit-testable.
 *
 * @package
 */

/**
 * The ApplePaySession version to construct. Part of the request contract: the
 * fields below are the ones this version accepts.
 */
export const APPLE_PAY_VERSION = 4;

/**
 * Builds the ApplePayPaymentRequest.
 *
 * Which address fields the sheet collects depends on who supplies the address. On
 * classic checkout the shopper already typed one into the WC form, so asking again
 * would be a second entry of the same data; everywhere else the sheet is the only
 * source. Apple never returns a billing email or phone, hence postalAddress alone
 * for billing — walletContacts.js backfills the email from the shipping contact.
 *
 * @param {Object} sessionConfig            - The v6 session config, as returned by
 *                                          formatConfigForPaymentRequest().
 * @param {Object} transaction              - What the shopper is about to pay.
 * @param {string} transaction.currencyCode - The shop currency.
 * @param {string} transaction.total        - The total as a decimal string.
 * @param {string} transaction.displayName  - The shop name, labelling the total.
 * @param {string} transaction.context      - The page context.
 * @return {Object} The ApplePayPaymentRequest.
 */
export function buildApplePayRequest(
	sessionConfig,
	{ currencyCode, total, displayName, context }
) {
	return {
		// The session config calls it merchantCountry; Apple's request wants
		// countryCode.
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
}
