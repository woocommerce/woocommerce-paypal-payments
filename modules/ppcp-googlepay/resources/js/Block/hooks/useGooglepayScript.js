import { useState, useEffect } from '@wordpress/element';
import { loadCustomScript } from '@paypal/paypal-js';

const useGooglepayScript = (
	componentDocument,
	buttonConfig,
	isPayPalLoaded
) => {
	const [ isGooglepayLoaded, setIsGooglepayLoaded ] = useState( false );

	useEffect( () => {
		if ( ! componentDocument ) {
			return;
		}

		const injectScriptToFrame = ( scriptSrc ) => {
			if ( document === componentDocument ) {
				return;
			}

			const script = document.querySelector(
				`script[src^="${ scriptSrc }"]`
			);

			if ( script ) {
				const newScript = componentDocument.createElement( 'script' );
				newScript.src = script.src;
				newScript.async = script.async;
				newScript.type = script.type;

				componentDocument.head.appendChild( newScript );
			} else {
				console.error( 'Script not found in the document:', scriptSrc );
			}
		};

		const loadGooglepayScript = async () => {
			if ( ! isPayPalLoaded ) {
				return;
			}

			if ( ! buttonConfig || ! buttonConfig.sdk_url ) {
				console.error( 'Invalid buttonConfig or missing sdk_url' );
				return;
			}

			try {
				await loadCustomScript( { url: buttonConfig.sdk_url } ).then(
					() => {
						injectScriptToFrame( buttonConfig.sdk_url );
					}
				);
				setIsGooglepayLoaded( true );
			} catch ( error ) {
				console.error( 'Failed to load Googlepay script:', error );
			}
		};

		loadGooglepayScript();
	}, [ componentDocument, buttonConfig, isPayPalLoaded ] );

	return isGooglepayLoaded;
};

export default useGooglepayScript;
