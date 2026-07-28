/**
 * Continuation-mode helpers.
 *
 * Continuation is the post-approval order review: the buyer approves in
 * PayPal, returns to the checkout page, and confirms there before the WC
 * order is created. Merchants opt in by leaving the "Pay Now Experience"
 * setting off (server side: final_review).
 *
 * @package
 */

/**
 * The URL to send the buyer to after approval when the final review is on.
 *
 * Mirrors v5's getCheckoutRedirectUrl: the checkout page the merchant has
 * designated (block or classic — whatever wc_get_checkout_url() resolves to),
 * plus a timestamp. The timestamp is not decorative: the continuation state is
 * assembled server-side on the next page render, so a cached page would drop
 * the buyer back into the express flow.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {string} The redirect URL.
 */
export function continuationRedirectUrl( config ) {
	const url = new URL( config.urls.checkout, window.location.origin );
	url.searchParams.append(
		'ppcp-continuation-redirect',
		Date.now().toString()
	);

	return url.toString();
}
