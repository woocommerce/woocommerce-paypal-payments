import { useState, useEffect } from '@wordpress/element';
import { loadPaypalScript } from '../../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';

const usePayPalScript = ( ppcpConfig ) => {
	const [ isLoaded, setIsLoaded ] = useState( false );

	useEffect( () => {
		if ( ! isLoaded ) {
			console.log( 'Loading PayPal script' );
			loadPaypalScript( ppcpConfig, () => {
				console.log( 'PayPal script loaded' );
				setIsLoaded( true );
			} );
		}
	}, [ ppcpConfig, isLoaded ] );

	return isLoaded;
};

export default usePayPalScript;
