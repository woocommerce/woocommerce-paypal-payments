/**
 * PayPal SDK v6 Bootstrap.
 *
 * Loads the PayPal JS SDK v6 core, fetches a browser-safe client token
 * from the backend, and creates the SDK instance.
 *
 * @package
 */

/**
 * @param {Object} config Localized script data from wc_ppcp_sdk_v6.
 */
( function ( config ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	/**
	 * Fetches the browser-safe client token from the WC AJAX endpoint.
	 *
	 * @return {Promise<string>} Resolves to the client token string.
	 */
	async function fetchClientToken() {
		const response = await fetch( config.ajax.client_token.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( {
				nonce: config.ajax.client_token.nonce,
			} ),
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
	 * @return {Promise<void>} Resolves when the script is loaded.
	 */
	function loadSdkScript() {
		return new Promise( ( resolve, reject ) => {
			if ( document.querySelector( 'script[src*="web-sdk/v6/core"]' ) ) {
				resolve();
				return;
			}

			const script = document.createElement( 'script' );
			script.src = config.sdk_url;
			script.async = true;
			script.onload = resolve;
			script.onerror = () =>
				reject( new Error( 'Failed to load PayPal SDK v6 script.' ) );
			document.head.appendChild( script );
		} );
	}

	/**
	 * Initializes the PayPal SDK v6 instance.
	 */
	async function init() {
		try {
			const [ , clientToken ] = await Promise.all( [
				loadSdkScript(),
				fetchClientToken(),
			] );

			if ( ! window.paypal?.createInstance ) {
				throw new Error(
					'PayPal SDK v6 global not found after script load.'
				);
			}

			const sdkInstance = await window.paypal.createInstance( {
				clientToken,
			} );

			window.ppcpSdkV6Instance = sdkInstance;

			document.dispatchEvent(
				new CustomEvent( 'ppcp-sdk-v6-ready', {
					detail: { sdkInstance },
				} )
			);
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( '[PPCP SDK v6]', error );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )( window.wc_ppcp_sdk_v6 );
