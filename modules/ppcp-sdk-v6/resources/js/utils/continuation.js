/**
 * Continuation-mode helpers.
 *
 * @package
 */

/**
 * The URL to send the buyer to after approval when a review step is required.
 *
 * The timestamp is a cache-buster: the continuation payload is assembled
 * server-side on the next render, so a cached page would drop the buyer back
 * into the express flow.
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
