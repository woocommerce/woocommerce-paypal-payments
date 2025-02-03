import {
	OnboardingStoreName,
	CommonStoreName,
	PaymentStoreName,
	SettingsStoreName,
	StylingStoreName,
	TodosStoreName,
} from './index';

export const addDebugTools = ( context, modules ) => {
	if ( ! context ) {
		return;
	}

	/*
     // TODO - enable this condition for version 3.0.1
     // In version 3.0.0 we want to have the debug tools available on every installation
     if ( ! context.debug ) { return }
     */

	const debugApi = ( window.ppcpDebugger = window.ppcpDebugger || {} );

	// Dump the current state of all our Redux stores.
	debugApi.dumpStore = async () => {
		/* eslint-disable no-console */
		if ( ! console?.groupCollapsed ) {
			console.error( 'console.groupCollapsed is not supported.' );
			return;
		}

		modules.forEach( ( module ) => {
			const storeName = module.STORE_NAME;
			const storeSelector = `wp.data.select( '${ storeName }' )`;
			console.group( `[STORE] ${ storeSelector }` );

			const dumpStore = ( selector ) => {
				const contents = wp.data.select( storeName )[ selector ]();

				console.groupCollapsed( `.${ selector }()` );
				console.table( contents );
				console.groupEnd();
			};

			Object.keys( module.selectors ).forEach( dumpStore );

			console.groupEnd();
		} );
		/* eslint-enable no-console */
	};

	// Reset all Redux stores to their initial state.
	debugApi.resetStore = () => {
		const stores = [];
		const { isConnected } = wp.data.select( CommonStoreName ).merchant();

		if ( isConnected ) {
			// Make sure the Onboarding wizard is "completed".
			const onboarding = wp.data.dispatch( OnboardingStoreName );
			onboarding.setCompleted( true );
			onboarding.persist();

			// Reset all stores, except for the onboarding store.
			stores.push( CommonStoreName );
			stores.push( PaymentStoreName );
			stores.push( SettingsStoreName );
			stores.push( StylingStoreName );
			stores.push( TodosStoreName );
		} else {
			// Only reset the common & onboarding stores to restart the onboarding wizard.
			stores.push( CommonStoreName );
			stores.push( OnboardingStoreName );
		}

		stores.forEach( ( storeName ) => {
			const store = wp.data.dispatch( storeName );

			// eslint-disable-next-line no-console
			console.log( `Reset store: ${ storeName }...` );

			try {
				store.reset();
				store.persist();
			} catch ( error ) {
				console.error( ' ... Reset failed, skipping this store' );
			}
		} );
	};

	// Disconnect the merchant and display the onboarding wizard.
	debugApi.disconnect = () => {
		const common = wp.data.dispatch( CommonStoreName );

		common.disconnectMerchant();

		// eslint-disable-next-line no-console
		console.log( 'Disconnected from PayPal. Reloading the page...' );

		window.location.reload();
	};

	// Enters or completes the onboarding wizard without changing anything else.
	debugApi.onboardingMode = ( state ) => {
		const onboarding = wp.data.dispatch( OnboardingStoreName );

		onboarding.setCompleted( ! state );
		onboarding.persist();
	};

	// Expose original debug API.
	Object.assign( context, debugApi );
};
