/**
 * Checks payment method eligibility via the v6 SDK.
 *
 * @package
 */

/**
 * Checks one method without letting a failure sink the whole check.
 *
 * Used for methods whose SDK component is only loaded on some pages: an
 * eligibility lookup for an absent component must not reject and take every
 * button down with it.
 *
 * @param {Object} methods - The findEligibleMethods result.
 * @param {string} method  - The method to check.
 * @return {boolean} Whether the method is eligible.
 */
function isEligibleSafely( methods, method ) {
	try {
		return methods.isEligible( method );
	} catch ( e ) {
		return false;
	}
}

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
		googlepay: isEligibleSafely( methods, 'googlepay' ),
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
