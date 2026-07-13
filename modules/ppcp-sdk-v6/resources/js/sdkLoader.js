/**
 * Fetches a client token and creates the PayPal SDK v6 instance.
 *
 * @package
 */

import { postJson } from './utils/api';

let sdkInstance = null;

const PAGE_TYPE_MAP = {
	product: 'product-details',
	cart: 'cart',
	checkout: 'checkout',
	'mini-cart': 'mini-cart',
};

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
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The resolved page context.
 * @return {Promise<Object>} The SDK instance.
 */
export async function loadSdkV6( config, context ) {
	if ( sdkInstance ) {
		return sdkInstance;
	}

	const [ , tokenData ] = await Promise.all( [
		loadSdkScript( config.sdk_url ),
		postJson( config.ajax.client_token ),
	] );

	if ( ! window.paypal?.createInstance ) {
		throw new Error( 'PayPal SDK v6 global not found after script load.' );
	}

	sdkInstance = await window.paypal.createInstance( {
		clientToken: tokenData.client_token,
		components: [ 'paypal-payments', 'venmo-payments' ],
		pageType: PAGE_TYPE_MAP[ context ] || 'checkout',
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
