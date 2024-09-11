import { useEffect, useRef, useState } from '@wordpress/element';
import Fastlane from '../../../../ppcp-axo/resources/js/Connection/Fastlane';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';

const useAxoBlockManager = ( axoConfig, ppcpConfig ) => {
	const [ fastlaneSdk, setFastlaneSdk ] = useState( null );
	const initializingRef = useRef( false );
	const configRef = useRef( { axoConfig, ppcpConfig } );

	useEffect( () => {
		const initFastlane = async () => {
			if ( initializingRef.current || fastlaneSdk ) {
				return;
			}

			initializingRef.current = true;
			log( 'Init Fastlane' );

			try {
				const fastlane = new Fastlane();

				if ( configRef.current.axoConfig.environment.is_sandbox ) {
					window.localStorage.setItem( 'axoEnv', 'sandbox' );
				}

				await fastlane.connect( {
					locale: configRef.current.ppcpConfig.locale,
					styles: configRef.current.ppcpConfig.styles,
				} );

				fastlane.setLocale( 'en_us' );

				setFastlaneSdk( fastlane );
			} catch ( error ) {
				console.error( 'Failed to initialize Fastlane:', error );
			} finally {
				initializingRef.current = false;
			}
		};

		initFastlane();
	}, [] );

	useEffect( () => {
		configRef.current = { axoConfig, ppcpConfig };
	}, [ axoConfig, ppcpConfig ] );

	return fastlaneSdk;
};

export default useAxoBlockManager;
