import { useState, useEffect } from '@wordpress/element';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';

const usePayPalCommerceGateway = ( initialConfig ) => {
	const [ isConfigLoaded, setIsConfigLoaded ] = useState( false );
	const [ ppcpConfig, setPpcpConfig ] = useState( initialConfig );

	useEffect( () => {
		const loadConfig = () => {
			if ( typeof window.PayPalCommerceGateway !== 'undefined' ) {
				setPpcpConfig( window.PayPalCommerceGateway );
				setIsConfigLoaded( true );
			} else {
				log( 'PayPal Commerce Gateway config not loaded.', 'error' );
			}
		};

		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', loadConfig );
		} else {
			loadConfig();
		}

		return () => {
			document.removeEventListener( 'DOMContentLoaded', loadConfig );
		};
	}, [] );

	return { isConfigLoaded, ppcpConfig };
};

export default usePayPalCommerceGateway;
