import { useState, useEffect } from '@wordpress/element';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';

/**
 * Custom hook to load and manage the PayPal Commerce Gateway configuration.
 *
 * @param {Object} initialConfig - Initial configuration object.
 * @return {Object} An object containing the loaded config and a boolean indicating if it's loaded.
 */
const usePayPalCommerceGateway = ( initialConfig ) => {
	const [ isConfigLoaded, setIsConfigLoaded ] = useState( false );
	const [ ppcpConfig, setPpcpConfig ] = useState( initialConfig );

	useEffect( () => {
		/**
		 * Function to load the PayPal Commerce Gateway configuration.
		 */
		const loadConfig = () => {
			if ( typeof window.PayPalCommerceGateway !== 'undefined' ) {
				setPpcpConfig( window.PayPalCommerceGateway );
				setIsConfigLoaded( true );
			} else {
				log( 'PayPal Commerce Gateway config not loaded.', 'error' );
			}
		};

		// Check if the DOM is still loading
		if ( document.readyState === 'loading' ) {
			// If it's loading, add an event listener for when the DOM is fully loaded
			document.addEventListener( 'DOMContentLoaded', loadConfig );
		} else {
			// If it's already loaded, call the loadConfig function immediately
			loadConfig();
		}

		// Cleanup function to remove the event listener
		return () => {
			document.removeEventListener( 'DOMContentLoaded', loadConfig );
		};
	}, [] );

	// Return the loaded configuration and the loading status
	return { isConfigLoaded, ppcpConfig };
};

export default usePayPalCommerceGateway;
