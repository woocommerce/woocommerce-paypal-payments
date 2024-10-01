import { useState, useEffect } from '@wordpress/element';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';
import { loadPaypalScript } from '../../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';

const usePayPalScript = ( ppcpConfig ) => {
	const [ isLoaded, setIsLoaded ] = useState( false );

	useEffect( () => {
		if ( ! isLoaded ) {
			log( 'Loading PayPal script' );
			loadPaypalScript( ppcpConfig, () => {
				log( 'PayPal script loaded' );
				setIsLoaded( true );
			} );
		}
	}, [ ppcpConfig, isLoaded ] );

	return isLoaded;
};

export default usePayPalScript;
