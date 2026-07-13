/**
 * Shared WC AJAX request helper.
 *
 * @package
 */

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

	const json = await response.json();

	if ( ! json.success ) {
		throw new Error( json.data?.message || '' );
	}

	return json.data;
}
