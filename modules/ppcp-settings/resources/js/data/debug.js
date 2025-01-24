import {
	OnboardingStoreName,
	CommonStoreName,
	PaymentStoreName,
	SettingsStoreName,
	StylingStoreName,
} from './index';
import { setCompleted } from './onboarding/actions';

export const addDebugTools = ( context, modules ) => {
	if ( ! context || ! context?.debug ) {
		return;
	}

	// Dump the current state of all our Redux stores.
	context.dumpStore = async () => {
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
	context.resetStore = () => {
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
		} else {
			// Only reset the common & onboarding stores to restart the onboarding wizard.
			stores.push( CommonStoreName );
			stores.push( OnboardingStoreName );
		}

		stores.forEach( ( storeName ) => {
			const store = wp.data.dispatch( storeName );

			// eslint-disable-next-line no-console
			console.log( `Reset store: ${ storeName }...` );

			store.reset();
			store.persist();
		} );
	};

	// Disconnect the merchant and display the onboarding wizard.
	context.disconnect = () => {
		const common = wp.data.dispatch( CommonStoreName );

		common.disconnectMerchant();

		// eslint-disable-next-line no-console
		console.log( 'Disconnected from PayPal. Reloading the page...' );

		window.location.reload();
	};

	// Enters or completes the onboarding wizard without changing anything else.
	context.onboardingMode = ( state ) => {
		const onboarding = wp.data.dispatch( OnboardingStoreName );

		onboarding.setCompleted( ! state );
		onboarding.persist();
	};
};
