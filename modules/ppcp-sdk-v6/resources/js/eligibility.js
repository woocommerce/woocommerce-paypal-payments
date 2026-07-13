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
 * @return {Promise<Object>} Eligibility keyed by method, plus payLaterDetails.
 */
export async function checkEligibility(
	sdkInstance,
	{ currencyCode, countryCode }
) {
	const eligibilityParams = { currencyCode };
	if ( countryCode ) {
		eligibilityParams.countryCode = countryCode;
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
