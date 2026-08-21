/**
 * Fetches a client token and creates the PayPal SDK v6 instance.
 *
 * @package
 */

import { postJson } from './utils/api';
import { loadScript } from './utils/scriptLoaders';
import { walletSdkComponents } from './wallets/walletRegistry';

const INSTANCE_KEY = '__ppcpV6InstancePromise';
const METADATA_ID_KEY = '__ppcpV6ClientMetadataId';

/**
 * The in-flight instance promise, shared across bundles.
 *
 * On window rather than in module scope because each webpack bundle gets its own
 * copy of this module: the ppcp-axo bundle asking for Fastlane must reuse the
 * instance this module's own bootstrap created, or the SDK script loads twice and
 * its custom elements fail to register a second time.
 *
 * @return {?Promise<Object>} The cached promise, or null.
 */
function cachedInstance() {
	return window[ INSTANCE_KEY ] || null;
}

/**
 * One client metadata id per page, shared across bundles.
 *
 * PayPal correlates it with the order for fraud checks, and createInstance
 * rejects outright when the fastlane component is requested without it.
 *
 * @return {string} The id.
 */
function clientMetadataId() {
	if ( ! window[ METADATA_ID_KEY ] ) {
		window[ METADATA_ID_KEY ] =
			window.crypto?.randomUUID?.() ||
			`ppcp-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	return window[ METADATA_ID_KEY ];
}

const PAGE_TYPE_MAP = {
	product: 'product-details',
	cart: 'cart',
	checkout: 'checkout',
	'pay-now': 'checkout',
	'mini-cart': 'mini-cart',
};

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
	if ( ! cachedInstance() ) {
		window[ INSTANCE_KEY ] = createInstance( config, context ).catch(
			( error ) => {
				delete window[ INSTANCE_KEY ];
				throw error;
			}
		);
	}

	return cachedInstance();
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
		loadScript( config.sdk_url ),
		postJson( config.ajax.client_token ),
	] );

	if ( ! window.paypal?.createInstance ) {
		throw new Error( 'PayPal SDK v6 global not found after script load.' );
	}

	const components = [ 'paypal-payments', 'venmo-payments' ];
	if ( config.card_fields?.enabled ) {
		components.push( 'card-fields' );
	}
	if ( config.fastlane?.enabled ) {
		components.push( 'fastlane' );
	}
	components.push( ...walletSdkComponents( config ) );

	const sdkInstance = await window.paypal.createInstance( {
		clientToken: tokenData.client_token,
		components,
		pageType: PAGE_TYPE_MAP[ context ] || 'checkout',
		locale: config.locale,
		clientMetadataId: clientMetadataId(),
	} );

	document.dispatchEvent(
		new CustomEvent( 'ppcp-sdk-v6-ready', {
			detail: { sdkInstance },
		} )
	);

	return sdkInstance;
}
