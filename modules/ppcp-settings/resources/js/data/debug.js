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

	const describe = ( fnName, fnInfo ) => {
		// eslint-disable-next-line no-console
		console.log( `\n%c${ fnName }:`, 'font-weight:bold', fnInfo, '\n\n' );
	};

	const debugApi = ( window.ppcpDebugger = window.ppcpDebugger || {} );

	// Dump the current state of all our Redux stores.
	debugApi.dumpStore = async ( cbFilter = null ) => {
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
				let contents = wp.data.select( storeName )[ selector ]();

				if ( cbFilter ) {
					contents = cbFilter( contents, selector, storeName );

					if ( undefined !== contents && null !== contents ) {
						console.log( `.${ selector }() [filtered]`, contents );
					}
				} else {
					console.groupCollapsed( `.${ selector }()` );
					console.table( contents );
					console.groupEnd();
				}
			};

			Object.keys( module.selectors ).forEach( dumpStore );

			console.groupEnd();
		} );
		/* eslint-enable no-console */
	};

	// Reset all Redux stores to their initial state.
	debugApi.resetStore = () => {
		const stores = [];

		describe(
			'resetStore',
			'Reset all Redux stores to their DEFAULT state, without changing any server-side data. The default state is defined in the JS code.'
		);

		const { completed } = wp.data
			.select( OnboardingStoreName )
			.persistentData();

		// Reset all stores, except for the onboarding store.
		stores.push( CommonStoreName );
		stores.push( PaymentStoreName );
		stores.push( SettingsStoreName );
		stores.push( StylingStoreName );
		stores.push( TodosStoreName );

		// Only reset the onboarding store when the wizard is not completed.
		if ( ! completed ) {
			stores.push( OnboardingStoreName );
		}

		stores.forEach( ( storeName ) => {
			const store = wp.data.dispatch( storeName );

			try {
				store.reset();

				// eslint-disable-next-line no-console
				console.log( `Done: Store '${ storeName }' reset` );
			} catch ( error ) {
				console.error(
					`Failed: Could not reset store '${ storeName }'`
				);
			}
		} );

		// eslint-disable-next-line no-console
		console.log( '---- Complete ----\n\n' );
	};

	debugApi.refreshStore = () => {
		const stores = [];

		describe(
			'refreshStore',
			'Refreshes all Redux details with details provided by the server. This has a similar effect as reloading the page without saving'
		);

		stores.push( CommonStoreName );
		stores.push( PaymentStoreName );
		stores.push( SettingsStoreName );
		stores.push( StylingStoreName );
		stores.push( TodosStoreName );
		stores.push( OnboardingStoreName );

		stores.forEach( ( storeName ) => {
			const store = wp.data.dispatch( storeName );

			try {
				store.refresh();

				// eslint-disable-next-line no-console
				console.log(
					`Done: Store '${ storeName }' refreshed from REST`
				);
			} catch ( error ) {
				console.error(
					`Failed: Could not refresh store '${ storeName }' from REST`
				);
			}
		} );

		// eslint-disable-next-line no-console
		console.log( '---- Complete ----\n\n' );
	};

	// Disconnect the merchant and display the onboarding wizard.
	debugApi.disconnect = () => {
		const common = wp.data.dispatch( CommonStoreName );

		describe();

		common.disconnectMerchant();

		// eslint-disable-next-line no-console
		console.log( 'Disconnected from PayPal. Reloading the page...' );

		window.location.reload();
	};

	// Enters or completes the onboarding wizard without changing anything else.
	debugApi.onboardingMode = ( state ) => {
		const onboarding = wp.data.dispatch( OnboardingStoreName );

		describe(
			'onboardingMode',
			'Toggle between onboarding wizard and the settings screen.'
		);

		onboarding.setPersistent( 'completed', ! state );
		onboarding.persist();
	};

	// Expose original debug API.
	Object.assign( context, debugApi );
};
