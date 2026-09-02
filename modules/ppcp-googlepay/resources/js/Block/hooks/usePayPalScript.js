import { useState, useEffect } from '@wordpress/element';
import { loadPayPalScript } from '@ppcp-button/Helper/PayPalScriptLoading';

const usePayPalScript = ( namespace, ppcpConfig ) => {
	const [ isPayPalLoaded, setIsPayPalLoaded ] = useState( false );

	// Absent when the v6 SDK owns the page: the v5 smart button is disabled
	// there and localizes no script data.
	if ( ppcpConfig.url_params ) {
		ppcpConfig.url_params.components += ',googlepay';
	}

	useEffect( () => {
		const loadScript = async () => {
			try {
				await loadPayPalScript( namespace, ppcpConfig );
				setIsPayPalLoaded( true );
			} catch ( error ) {
				console.error( `Error loading PayPal script: ${ error }` );
			}
		};

		loadScript();
	}, [ namespace, ppcpConfig ] );

	return isPayPalLoaded;
};

export default usePayPalScript;
