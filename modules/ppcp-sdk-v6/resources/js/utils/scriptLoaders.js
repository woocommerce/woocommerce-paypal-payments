/**
 * Loaders for the external scripts this module depends on.
 *
 * @package
 */

const CACHE_KEY = '__ppcpV6ScriptPromises';

/**
 * The per-URL load promises, shared across bundles.
 *
 * On window rather than in module scope because each webpack bundle gets its own
 * copy of this module: a second bundle asking for the same URL would otherwise
 * inject the tag again, and these SDKs register custom elements, which throws on
 * the duplicate registration.
 *
 * @return {Object} The cache.
 */
function scriptPromiseCache() {
	if ( ! window[ CACHE_KEY ] ) {
		window[ CACHE_KEY ] = {};
	}

	return window[ CACHE_KEY ];
}

/**
 * Appends a script tag and resolves once it loaded.
 *
 * The load promise is cached per URL (not sniffed from the DOM) so a
 * failed load rejects every awaiting caller and clears the cache,
 * allowing a later retry to insert a fresh script tag.
 *
 * @param {string} url - The script URL.
 * @return {Promise<void>} Resolves when the script is loaded.
 */
export function loadScript( url ) {
	const scriptPromises = scriptPromiseCache();

	if ( ! scriptPromises[ url ] ) {
		scriptPromises[ url ] = new Promise( ( resolve, reject ) => {
			const script = document.createElement( 'script' );
			script.src = url;
			script.async = true;
			script.onload = resolve;
			script.onerror = () => {
				script.remove();
				delete scriptPromises[ url ];
				reject( new Error( `Failed to load script: ${ url }` ) );
			};
			document.head.appendChild( script );
		} );
	}

	return scriptPromises[ url ];
}

/**
 * Loads Google's pay.js and verifies the Google Pay API global is usable.
 *
 * The googlepay-payments bundle neither loads pay.js nor touches PaymentsClient:
 * the merchant drives the Google button and payment sheet.
 *
 * @param {string} sdkUrl - The pay.js URL.
 * @return {Promise<void>} Resolves once google.payments.api is available.
 */
export async function loadGoogleSdk( sdkUrl ) {
	await loadScript( sdkUrl );

	if ( ! window.google?.payments?.api?.PaymentsClient ) {
		throw new Error( 'Google Pay global not found after script load.' );
	}
}
