/**
 * Loads a PayPal SDK v6 instance for block editor button previews. Sibling of
 * messages/editorPreview.js; requests only the payments component (which owns the button
 * elements). The preview binds no session, so the button only shows its appearance.
 *
 * @package
 */

import { loadScript } from '../utils/scriptLoaders';

const INSTANCE_KEY = '__ppcpV6EditorButtonsPromise';

/**
 * Loads the SDK into `targetWindow` and prepares its button component.
 *
 * Memoized per window because the editor canvas is an iframe with its own custom element
 * registry, so `<paypal-button>` only upgrades in the window that loaded the SDK.
 *
 * @param {Window} targetWindow - The window the preview renders in.
 * @param {Object} options      - See createButtons().
 * @return {Promise<void>} Resolves once `<paypal-button>` can render.
 */
export function loadEditorButtons( targetWindow, options ) {
	if ( ! targetWindow[ INSTANCE_KEY ] ) {
		targetWindow[ INSTANCE_KEY ] = createButtons(
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
 * Loads the script and creates the instance.
 *
 * @param {Window} targetWindow     - The window to load into.
 * @param {Object} options          - The preview options.
 * @param {string} options.sdkUrl   - The v6 core script URL.
 * @param {string} options.clientId - The merchant's public client id.
 * @param {string} options.locale   - The BCP 47 locale.
 * @param {string} options.pageType - The v6 page type.
 * @return {Promise<void>} Resolves once the button component is ready.
 */
async function createButtons(
	targetWindow,
	{ sdkUrl, clientId, locale, pageType }
) {
	await loadScript( sdkUrl, targetWindow );

	if ( ! targetWindow.paypal?.createInstance ) {
		throw new Error( 'PayPal SDK v6 global not found after script load.' );
	}

	await targetWindow.paypal.createInstance( {
		clientId,
		components: [ 'paypal-payments' ],
		pageType,
		locale,
	} );
}
