/**
 * Loads a PayPal SDK v6 messages instance for block editor previews.
 *
 * Separate from sdkLoader.js: v6 does not own admin pages, so there is no
 * wc_ppcp_sdk_v6 config and no client-token nonce here. createInstance()
 * takes the public client id instead, and only the messages component is
 * requested.
 *
 * Free of `@wordpress/*` imports, like renderer.js.
 *
 * @package
 */

import { loadScript } from '../utils/scriptLoaders';

const INSTANCE_KEY = '__ppcpV6EditorMessagesPromise';

/**
 * Loads the SDK into `targetWindow` and prepares its messages component.
 *
 * Per window, not per page: the editor canvas is an iframe with its own custom
 * element registry, so `<paypal-message>` only upgrades in a window that loaded
 * the SDK itself. Memoized on that window so every block in it shares one
 * instance; reset on failure to allow a retry.
 *
 * @param {Window} targetWindow     - The window the preview renders in.
 * @param {Object} options          - The preview options.
 * @param {string} options.sdkUrl   - The v6 core script URL.
 * @param {string} options.clientId - The merchant's public client id.
 * @param {string} options.currency - The currency code.
 * @param {string} options.locale   - The BCP 47 locale.
 * @param {string} options.pageType - The v6 page type.
 * @return {Promise<void>} Resolves once `<paypal-message>` can render.
 */
export function loadEditorMessages( targetWindow, options ) {
	if ( ! targetWindow[ INSTANCE_KEY ] ) {
		targetWindow[ INSTANCE_KEY ] = createMessages(
			targetWindow,
			options
		).catch( ( error ) => {
			delete targetWindow[ INSTANCE_KEY ];
			throw error;
		} );
	}

	return targetWindow[ INSTANCE_KEY ];
}

/**
 * Performs the script load and instance creation.
 *
 * @param {Window} targetWindow     - The window to load into.
 * @param {Object} options          - See loadEditorMessages().
 * @param {string} options.sdkUrl   - The v6 core script URL.
 * @param {string} options.clientId - The merchant's public client id.
 * @param {string} options.currency - The currency code.
 * @param {string} options.locale   - The BCP 47 locale.
 * @param {string} options.pageType - The v6 page type.
 * @return {Promise<void>} Resolves once the messages component is ready.
 */
async function createMessages(
	targetWindow,
	{ sdkUrl, clientId, currency, locale, pageType }
) {
	await loadScript( sdkUrl, targetWindow );

	if ( ! targetWindow.paypal?.createInstance ) {
		throw new Error( 'PayPal SDK v6 global not found after script load.' );
	}

	const sdkInstance = await targetWindow.paypal.createInstance( {
		clientId,
		components: [ 'paypal-messages' ],
		pageType,
		locale,
	} );

	sdkInstance.createPayPalMessages( { currencyCode: currency } );
}
