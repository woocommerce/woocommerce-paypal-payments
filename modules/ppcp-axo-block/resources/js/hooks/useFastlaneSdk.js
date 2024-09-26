import { useEffect, useRef, useState, useMemo } from '@wordpress/element';
import Fastlane from '../../../../ppcp-axo/resources/js/Connection/Fastlane';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';
import { useDeleteEmptyKeys } from './useDeleteEmptyKeys';

const useFastlaneSdk = ( axoConfig, ppcpConfig ) => {
	const [ fastlaneSdk, setFastlaneSdk ] = useState( null );
	const initializingRef = useRef( false );
	const configRef = useRef( { axoConfig, ppcpConfig } );
	const deleteEmptyKeys = useDeleteEmptyKeys();

	const styleOptions = useMemo( () => {
		return deleteEmptyKeys( configRef.current.axoConfig.style_options );
	}, [ deleteEmptyKeys ] );

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
					styles: styleOptions,
				} );

				fastlane.setLocale( 'en_us' );

				setFastlaneSdk( fastlane );
			} catch ( error ) {
				log( `Failed to initialize Fastlane: ${ error }`, 'error' );
			} finally {
				initializingRef.current = false;
			}
		};

		initFastlane();
	}, [ fastlaneSdk, styleOptions ] );

	useEffect( () => {
		configRef.current = { axoConfig, ppcpConfig };
	}, [ axoConfig, ppcpConfig ] );

	return fastlaneSdk;
};

export default useFastlaneSdk;
