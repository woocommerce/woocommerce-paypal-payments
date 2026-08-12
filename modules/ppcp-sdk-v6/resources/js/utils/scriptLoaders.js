/**
 * Loaders for the external scripts this module depends on.
 *
 * @package
 */

const scriptPromises = {};

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
 * Unlike Apple Pay, whose component bundle loads Apple's SDK itself, the
 * googlepay-payments bundle neither loads pay.js nor touches PaymentsClient:
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
