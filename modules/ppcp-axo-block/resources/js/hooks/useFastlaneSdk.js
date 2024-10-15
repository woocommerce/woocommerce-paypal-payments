import { useEffect, useRef, useState, useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import Fastlane from '../../../../ppcp-axo/resources/js/Connection/Fastlane';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';
import { useDeleteEmptyKeys } from './useDeleteEmptyKeys';
import { STORE_NAME } from '../stores/axoStore';

/**
 * Custom hook to initialize and manage the Fastlane SDK.
 *
 * @param {string} namespace  - Namespace for the PayPal script.
 * @param {Object} axoConfig  - Configuration for AXO.
 * @param {Object} ppcpConfig - Configuration for PPCP.
 * @return {Object|null} The initialized Fastlane SDK instance or null.
 */
const useFastlaneSdk = ( namespace, axoConfig, ppcpConfig ) => {
	const [ fastlaneSdk, setFastlaneSdk ] = useState( null );
	const initializingRef = useRef( false );
	const configRef = useRef( { axoConfig, ppcpConfig } );
	const deleteEmptyKeys = useDeleteEmptyKeys();

	const { isPayPalLoaded } = useSelect(
		( select ) => ( {
			isPayPalLoaded: select( STORE_NAME ).getIsPayPalLoaded(),
		} ),
		[]
	);

	const styleOptions = useMemo( () => {
		return deleteEmptyKeys( configRef.current.axoConfig.style_options );
	}, [ deleteEmptyKeys ] );

	// Effect to initialize Fastlane SDK
	useEffect( () => {
		const initFastlane = async () => {
			if ( initializingRef.current || fastlaneSdk || ! isPayPalLoaded ) {
				return;
			}

			initializingRef.current = true;
			log( 'Init Fastlane' );

			try {
				const fastlane = new Fastlane( namespace );

				// Set sandbox environment if configured
				if ( configRef.current.axoConfig.environment.is_sandbox ) {
					window.localStorage.setItem( 'axoEnv', 'sandbox' );
				}

				// Connect to Fastlane with locale and style options
				await fastlane.connect( {
					locale: configRef.current.ppcpConfig.locale,
					styles: styleOptions,
				} );

				// Set locale (hardcoded to 'en_us' for now)
				fastlane.setLocale( 'en_us' );

				setFastlaneSdk( fastlane );
			} catch ( error ) {
				log( `Failed to initialize Fastlane: ${ error }`, 'error' );
			} finally {
				initializingRef.current = false;
			}
		};

		initFastlane();
	}, [ fastlaneSdk, styleOptions, isPayPalLoaded, namespace ] );

	// Effect to update the config ref when configs change
	useEffect( () => {
		configRef.current = { axoConfig, ppcpConfig };
	}, [ axoConfig, ppcpConfig ] );

	return fastlaneSdk;
};

export default useFastlaneSdk;
