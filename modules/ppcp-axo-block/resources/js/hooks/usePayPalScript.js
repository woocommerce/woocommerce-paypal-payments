import { useState, useEffect } from '@wordpress/element';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';
import { loadPaypalScript } from '../../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';

/**
 * Custom hook to load the PayPal script.
 *
 * @param {Object} ppcpConfig - Configuration object for PayPal script.
 * @return {boolean} True if the PayPal script has loaded, false otherwise.
 */
const usePayPalScript = ( ppcpConfig ) => {
	const [ isLoaded, setIsLoaded ] = useState( false );

	useEffect( () => {
		if ( ! isLoaded ) {
			log( 'Loading PayPal script' );

			// Load the PayPal script using the provided configuration
			loadPaypalScript( ppcpConfig, () => {
				log( 'PayPal script loaded' );
				setIsLoaded( true );
			} );
		}
	}, [ ppcpConfig, isLoaded ] );

	return isLoaded;
};

export default usePayPalScript;
