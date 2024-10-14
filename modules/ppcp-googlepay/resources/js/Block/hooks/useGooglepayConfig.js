import { useState, useEffect } from '@wordpress/element';

const useGooglepayConfig = ( namespace, isGooglepayLoaded ) => {
	const [ googlePayConfig, setGooglePayConfig ] = useState( null );

	useEffect( () => {
		const fetchConfig = async () => {
			if ( ! isGooglepayLoaded ) {
				return;
			}

			try {
				const config = await window[ namespace ].Googlepay().config();
				setGooglePayConfig( config );
			} catch ( error ) {
				console.error( 'Failed to fetch Google Pay config:', error );
			}
		};

		fetchConfig();
	}, [ namespace, isGooglepayLoaded ] );

	return googlePayConfig;
};

export default useGooglepayConfig;
