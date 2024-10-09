import { useState, useEffect } from '@wordpress/element';
import { loadCustomScript } from '@paypal/paypal-js';

const useGooglepayScript = ( buttonConfig, isPayPalLoaded ) => {
	const [ isGooglepayLoaded, setIsGooglepayLoaded ] = useState( false );

	useEffect( () => {
		const loadGooglepayScript = async () => {
			if ( ! isPayPalLoaded ) {
				return;
			}

			if ( ! buttonConfig || ! buttonConfig.sdk_url ) {
				console.error( 'Invalid buttonConfig or missing sdk_url' );
				return;
			}

			try {
				await loadCustomScript( { url: buttonConfig.sdk_url } );
				setIsGooglepayLoaded( true );
			} catch ( error ) {
				console.error( 'Failed to load Googlepay script:', error );
			}
		};

		loadGooglepayScript();
	}, [ buttonConfig, isPayPalLoaded ] );

	return isGooglepayLoaded;
};

export default useGooglepayScript;
