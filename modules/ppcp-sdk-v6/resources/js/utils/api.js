/**
 * Shared WC AJAX request helper.
 */

/**
 * The parsed JSON body of a WC AJAX response.
 *
 * A bare SyntaxError names neither the call nor its outcome, so a non-JSON
 * answer is rethrown carrying the status.
 *
 * @param {Response} response - The answered request.
 * @return {Promise<Object>} The parsed body.
 * @throws {Error} When the answer is not JSON, or is the bare `0` WordPress
 *                 replies with for an unregistered ajax action.
 */
async function readJsonBody( response ) {
	let json = null;

	try {
		json = await response.json();
	} catch ( parseError ) {
		// Not JSON.
	}

	if ( json ) {
		return json;
	}

	const error = new Error(
		`Endpoint returned no usable JSON (HTTP ${ response.status }).`
	);
	error.status = response.status;

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

	const json = await readJsonBody( response );

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
