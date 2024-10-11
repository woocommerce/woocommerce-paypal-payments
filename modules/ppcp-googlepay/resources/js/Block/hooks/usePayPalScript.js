import { useState, useEffect } from '@wordpress/element';
import { loadPayPalScript } from '../../../../../ppcp-button/resources/js/modules/Helper/PayPalScriptLoading';

const usePayPalScript = ( namespace, ppcpConfig ) => {
	const [ isPayPalLoaded, setIsPayPalLoaded ] = useState( false );

	ppcpConfig.url_params.components += ',googlepay';

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
