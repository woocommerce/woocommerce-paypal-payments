/**
 * Reducer: Defines store structure and state updates for this module.
 *
 * Manages both transient (temporary) and persistent (saved) state.
 * The initial state must define all properties, as dynamic additions are not supported.
 *
 * @file
 */

import { createReducer, createReducerSetters } from '../utils';
import ACTION_TYPES from './action-types';

// Store structure.

const defaultTransient = Object.freeze( {
	isReady: false,
	manualClientId: '',
	manualClientSecret: '',

	// Read only values, provided by the server.
	flags: Object.freeze( {
		canUseCasualSelling: false,
		canUseVaulting: false,
		canUseCardPayments: false,
		canUseSubscriptions: false,
	} ),
} );

const defaultPersistent = Object.freeze( {
	completed: false,
	step: 0,
	isCasualSeller: null, // null value will uncheck both options in the UI.
	areOptionalPaymentMethodsEnabled: null,
	products: [],
} );

// Reducer logic.

const [ changeTransient, changePersistent ] = createReducerSetters(
	defaultTransient,
	defaultPersistent
);

const onboardingReducer = createReducer( defaultTransient, defaultPersistent, {
	[ ACTION_TYPES.SET_TRANSIENT ]: ( state, payload ) =>
		changeTransient( state, payload ),

	[ ACTION_TYPES.SET_PERSISTENT ]: ( state, payload ) =>
		changePersistent( state, payload ),

	[ ACTION_TYPES.RESET ]: ( state ) => {
		const cleanState = changeTransient(
			changePersistent( state, defaultPersistent ),
			defaultTransient
		);

		// Keep "read-only" details and initialization flags.
		cleanState.flags = { ...state.flags };
		cleanState.isReady = true;

		return cleanState;
	},

	[ ACTION_TYPES.HYDRATE ]: ( state, payload ) => {
		const newState = changePersistent( state, payload.data );

		// Flags are not updated by `changePersistent()`.
		if ( payload.flags ) {
			newState.flags = Object.freeze( {
				...newState.flags,
				...payload.flags,
			} );
		}

		return newState;
	},
} );

export default onboardingReducer;
