/**
 * Fetches a client token and creates the PayPal SDK v6 instance.
 *
 * @package
 */

let sdkInstance = null;

/**
 * Fetches a browser-safe client token from the server.
 *
 * @param {Object} tokenConfig          - Token endpoint configuration.
 * @param {string} tokenConfig.endpoint - The AJAX endpoint URL.
 * @param {string} tokenConfig.nonce    - The nonce for the request.
 * @return {Promise<string>} The client token.
 */
async function fetchClientToken( { endpoint, nonce } ) {
	const response = await fetch( endpoint, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify( { nonce } ),
	} );

	const json = await response.json();

	if ( ! json.success ) {
		throw new Error(
			json.data?.message || 'Failed to fetch client token.'
		);
	}

	return json.data.client_token;
}

/**
 * Dynamically loads the PayPal SDK v6 core script.
 *
 * @param {string} sdkUrl - The SDK URL.
 * @return {Promise<void>} Resolves when the script is loaded.
 */
function loadSdkScript( sdkUrl ) {
	return new Promise( ( resolve, reject ) => {
		if ( document.querySelector( 'script[src*="web-sdk/v6/core"]' ) ) {
			resolve();
			return;
		}

		const script = document.createElement( 'script' );
		script.src = sdkUrl;
		script.async = true;
		script.onload = resolve;
		script.onerror = () =>
			reject( new Error( 'Failed to load PayPal SDK v6 script.' ) );
		document.head.appendChild( script );
	} );
}

/**
 * Loads the SDK script and creates an instance.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {Promise<Object>} The SDK instance.
 */
export async function loadSdkV6( config ) {
	if ( sdkInstance ) {
		return sdkInstance;
	}

	const [ , clientToken ] = await Promise.all( [
		loadSdkScript( config.sdk_url ),
		fetchClientToken( config.ajax.client_token ),
	] );

	if ( ! window.paypal?.createInstance ) {
		throw new Error( 'PayPal SDK v6 global not found after script load.' );
	}

	const pageTypeMap = {
		product: 'product-details',
		cart: 'cart',
		checkout: 'checkout',
		'mini-cart': 'cart',
	};

	sdkInstance = await window.paypal.createInstance( {
		clientToken,
		components: [ 'paypal-payments', 'venmo-payments' ],
		pageType: pageTypeMap[ config.page_context ] || 'checkout',
		locale: config.locale,
	} );

	return sdkInstance;
}

/**
 * Returns the cached SDK instance or null.
 *
 * @return {Object|null} The SDK instance or null.
 */
export function getSdkInstance() {
	return sdkInstance;
}
