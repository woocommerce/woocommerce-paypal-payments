import { useEffect, useState } from '@wordpress/element';
import Fastlane from '../../../../ppcp-axo/resources/js/Connection/Fastlane';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';

const useAxoBlockManager = ( axoConfig, ppcpConfig ) => {
	const [ fastlaneSdk, setFastlaneSdk ] = useState( null );
	const [ initialized, setInitialized ] = useState( false );

	useEffect( () => {
		const initFastlane = async () => {
			log( 'Init Fastlane' );

			if ( initialized ) {
				return;
			}

			setInitialized( true );

			const fastlane = new Fastlane();

			if ( axoConfig.environment.is_sandbox ) {
				window.localStorage.setItem( 'axoEnv', 'sandbox' );
			}

			await fastlane.connect( {
				locale: ppcpConfig.locale,
				styles: ppcpConfig.styles,
			} );

			fastlane.setLocale( 'en_us' );

			setFastlaneSdk( fastlane );
		};

		initFastlane();
	}, [ axoConfig, ppcpConfig, initialized ] );

	return fastlaneSdk;
};

export default useAxoBlockManager;
