/**
 * Fetches a client token and creates the PayPal SDK v6 instance.
 *
 * @package
 */

import { postJson } from './utils/api';

const scriptPromises = {};
let instancePromise = null;

const PAGE_TYPE_MAP = {
	product: 'product-details',
	cart: 'cart',
	checkout: 'checkout',
	'pay-now': 'checkout',
	'mini-cart': 'mini-cart',
};

/**
 * Dynamically loads the PayPal SDK v6 core script.
 *
 * The load promise is cached per URL (not sniffed from the DOM) so a
 * failed load rejects every awaiting caller and clears the cache,
 * allowing a later retry to insert a fresh script tag.
 *
 * @param {string} sdkUrl - The SDK URL.
 * @return {Promise<void>} Resolves when the script is loaded.
 */
function loadSdkScript( sdkUrl ) {
	if ( ! scriptPromises[ sdkUrl ] ) {
		scriptPromises[ sdkUrl ] = new Promise( ( resolve, reject ) => {
			const script = document.createElement( 'script' );
			script.src = sdkUrl;
			script.async = true;
			script.onload = resolve;
			script.onerror = () => {
				script.remove();
				delete scriptPromises[ sdkUrl ];
				reject( new Error( 'Failed to load PayPal SDK v6 script.' ) );
			};
			document.head.appendChild( script );
		} );
	}

	return scriptPromises[ sdkUrl ];
}

/**
 * Loads the SDK script, fetches a client token and creates the instance.
 *
 * Memoized on the in-flight promise so concurrent callers share one
 * token fetch and one instance; reset on failure to allow retries.
 * Dispatches the ppcp-sdk-v6-ready event once, when the instance exists.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context used for the SDK pageType.
 * @return {Promise<Object>} The SDK instance.
 */
export function loadSdkV6( config, context ) {
	if ( ! instancePromise ) {
		instancePromise = createInstance( config, context ).catch(
			( error ) => {
				instancePromise = null;
				throw error;
			}
		);
	}

	return instancePromise;
}

/**
 * Performs the actual script load, token fetch and instance creation.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} context - The page context used for the SDK pageType.
 * @return {Promise<Object>} The SDK instance.
 */
async function createInstance( config, context ) {
	const [ , tokenData ] = await Promise.all( [
		loadSdkScript( config.sdk_url ),
		postJson( config.ajax.client_token ),
	] );

	if ( ! window.paypal?.createInstance ) {
		throw new Error( 'PayPal SDK v6 global not found after script load.' );
	}

	const components = [ 'paypal-payments', 'venmo-payments' ];
	if ( config.card_fields?.enabled ) {
		components.push( 'card-fields' );
	}

	const sdkInstance = await window.paypal.createInstance( {
		clientToken: tokenData.client_token,
		components,
		pageType: PAGE_TYPE_MAP[ context ] || 'checkout',
		locale: config.locale,
	} );

	document.dispatchEvent(
		new CustomEvent( 'ppcp-sdk-v6-ready', {
			detail: { sdkInstance },
		} )
	);

	return sdkInstance;
}
