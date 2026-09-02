/**
 * Shared WC AJAX request helper.
 */

// Enough of an unexpected body to recognize a cache or WAF error page by.
const MAX_BODY_SNIPPET = 200;

/**
 * The parsed JSON body of a WC AJAX response.
 *
 * Cloned before parsing, because parsing consumes the body: an answer that is
 * not JSON can then still be quoted back, instead of surfacing only as a
 * SyntaxError naming neither the endpoint nor the status.
 *
 * @param {Response} response - The answered request.
 * @param {string}   endpoint - The URL, named in the error.
 * @return {Promise<Object>} The parsed body.
 * @throws {Error} When the answer is not JSON, or is the bare `0` WordPress
 *                 replies with for an unregistered ajax action.
 */
async function readJsonBody( response, endpoint ) {
	const unparsed = response.clone();
	let json = null;

	try {
		json = await response.json();
	} catch ( parseError ) {
		// Not JSON. Reported below, with the body quoted back.
	}

	if ( json ) {
		return json;
	}

	const error = new Error(
		`Endpoint returned no usable JSON (HTTP ${ response.status }).`
	);
	error.status = response.status;
	error.endpoint = endpoint;
	error.bodySnippet = '';

	try {
		const text = await unparsed.text();
		error.bodySnippet = text.slice( 0, MAX_BODY_SNIPPET );
	} catch ( readError ) {
		// A body that cannot be read still leaves the status and endpoint.
	}

	throw error;
}

/**
 * Posts a JSON body to a WC AJAX endpoint and returns the response data.
 *
 * @param {Object} ajaxConfig          - Endpoint configuration.
 * @param {string} ajaxConfig.endpoint - The endpoint URL.
 * @param {string} ajaxConfig.nonce    - The nonce for the request.
 * @param {Object} [body]              - Additional request body fields.
 * @return {Promise<Object|Array|null>} The `data` member of the JSON response.
 * @throws {Error} When the response is not successful.
 */
export async function postJson( { endpoint, nonce }, body = {} ) {
	const response = await fetch( endpoint, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify( { nonce, ...body } ),
	} );

	const json = await readJsonBody( response, endpoint );

	if ( ! json.success ) {
		const error = new Error( json.data?.message || '' );
		// Server-provided messages are translated and shopper-appropriate;
		// the error handler shows them verbatim, unlike internal messages.
		error.isUserFacing = Boolean( json.data?.message );
		// Validation responses carry a message list and a refresh flag
		// (expired session); forwarded for v5-parity error rendering.
		error.errors = json.data?.errors;
		error.refresh = Boolean( json.data?.refresh );
		error.status = response.status;
		error.endpoint = endpoint;
		throw error;
	}

	return json.data;
}

/**
 * Posts a JSON body to a WC Store API endpoint, authenticating with the
 * Store API nonce header.
 *
 * @param {Object} storeApi - The wc_store_api config (urls + nonce).
 * @param {string} url      - The endpoint URL.
 * @param {Object} body     - The request body.
 * @return {Promise<Object|null>} The parsed response body, or null when it is not JSON.
 * @throws {Error} When the response is not OK.
 */
export async function postStoreApi( storeApi, url, body ) {
	const response = await fetch( url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			Nonce: storeApi.nonce,
		},
		body: JSON.stringify( body ),
	} );

	const json = await response.json().catch( () => null );

	if ( ! response.ok ) {
		const error = new Error( json?.message || 'Store API request failed.' );
		error.isUserFacing = Boolean( json?.message );
		error.code = json?.code;
		throw error;
	}

	return json;
}

/**
 * Whether jQuery is available; logs a console error when it is not.
 *
 * The classic-page live-update bindings are jQuery-based; a missing
 * jQuery must be loud instead of a silent partial no-op.
 *
 * @return {boolean} True when jQuery is available.
 */
export function hasJQuery() {
	if ( typeof jQuery === 'undefined' ) {
		// eslint-disable-next-line no-console
		console.error( '[PPCP SDK v6] jQuery not present' );
		return false;
	}

	return true;
}
