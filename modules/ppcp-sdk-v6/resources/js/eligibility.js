/**
 * Checks payment method eligibility via the v6 SDK.
 *
 * @package
 */

/**
 * Checks which payment methods are eligible.
 *
 * @param {Object} sdkInstance           - The PayPal SDK v6 instance.
 * @param {Object} options               - Eligibility options.
 * @param {string} options.currencyCode  - ISO 4217 currency code.
 * @param {string} [options.countryCode] - ISO 3166-1 alpha-2 country code.
 * @param {string} [options.amount]      - Transaction amount, affects Pay Later thresholds.
 * @return {Promise<Object>} Eligibility keyed by method, plus payLaterDetails.
 */
export async function checkEligibility(
	sdkInstance,
	{ currencyCode, countryCode, amount }
) {
	const eligibilityParams = { currencyCode };
	if ( countryCode ) {
		eligibilityParams.countryCode = countryCode;
	}
	if ( amount ) {
		eligibilityParams.amount = amount;
	}

	const methods = await sdkInstance.findEligibleMethods( eligibilityParams );

	const result = {
		paypal: methods.isEligible( 'paypal' ),
		venmo: methods.isEligible( 'venmo' ),
		paylater: methods.isEligible( 'paylater' ),
		payLaterDetails: null,
	};

	if ( result.paylater ) {
		try {
			result.payLaterDetails = methods.getDetails( 'paylater' );
		} catch ( e ) {
			// getDetails may not be available for all regions.
		}
	}

	return result;
}

/**
 * Checks eligibility for the "save payment method without purchase" (vault)
 * flow used on the My Account › Add Payment Method page.
 *
 * Unlike the one-time flow, eligibility is queried with the dedicated
 * VAULT_WITHOUT_PAYMENT payment flow and is amount-independent.
 *
 * @param {Object} sdkInstance          - The PayPal SDK v6 instance.
 * @param {Object} options              - Eligibility options.
 * @param {string} options.currencyCode - ISO 4217 currency code.
 * @return {Promise<Object>} Eligibility keyed by method (currently paypal).
 */
export async function checkVaultEligibility( sdkInstance, { currencyCode } ) {
	const methods = await sdkInstance.findEligibleMethods( {
		currencyCode,
		paymentFlow: 'VAULT_WITHOUT_PAYMENT',
	} );

	return {
		paypal: methods.isEligible( 'paypal' ),
	};
}
