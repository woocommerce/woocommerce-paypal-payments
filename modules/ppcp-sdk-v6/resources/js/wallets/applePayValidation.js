/**
 * Reports the outcome of Apple Pay merchant validation back to the server.
 *
 * Not part of taking the payment: it records whether the merchant's domain is
 * registered with Apple, which drives the admin "domain not validated" notice.
 * Without it that warning would stay up while Apple Pay works fine.
 *
 * @package
 */

/**
 * Records whether merchant validation succeeded.
 *
 * Never throws: this is bookkeeping for an admin notice, and a shopper mid-sheet
 * must not be shown an error because it failed.
 *
 * @param {Object}  config  - The wc_ppcp_sdk_v6 config object.
 * @param {boolean} isValid - Whether validateMerchant() succeeded.
 * @return {Promise<void>} Resolves once reported, or once the attempt failed.
 */
export async function recordDomainValidation( config, isValid ) {
	const validation = config.apple_pay?.validation;
	if ( ! validation?.endpoint ) {
		return;
	}

	try {
		await fetch( validation.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			// Form-encoded, not JSON: admin-ajax reads the action and the nonce
			// out of $_POST, and parses the flag with FILTER_VALIDATE_BOOLEAN,
			// which is what makes the stringified boolean acceptable.
			body: new URLSearchParams( {
				action: validation.action,
				'woocommerce-process-checkout-nonce': validation.nonce,
				validation: isValid,
			} ).toString(),
		} );
	} catch ( error ) {
		// Bookkeeping only: the shopper's payment does not depend on it.
	}
}
