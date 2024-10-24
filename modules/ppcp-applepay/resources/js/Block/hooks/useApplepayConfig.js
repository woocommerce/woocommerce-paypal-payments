import { useState, useEffect } from '@wordpress/element';

const useApplepayConfig = ( namespace, isApplepayLoaded ) => {
	const [ applePayConfig, setApplePayConfig ] = useState( null );

	useEffect( () => {
		const fetchConfig = async () => {
			if ( ! isApplepayLoaded ) {
				return;
			}

			try {
				const config = await window[ namespace ].Applepay().config();
				setApplePayConfig( config );
			} catch ( error ) {
				console.error( 'Failed to fetch Apple Pay config:', error );
			}
		};

		fetchConfig();
	}, [ namespace, isApplepayLoaded ] );

	return applePayConfig;
};

export default useApplepayConfig;
