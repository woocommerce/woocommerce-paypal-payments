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

// Transient: Values that are _not_ saved to the DB (like app lifecycle-flags).
const defaultTransient = Object.freeze( {
	isReady: false,
} );

// Persistent: Values that are loaded from the DB.
const defaultPersistent = Object.freeze( {
	'ppcp-gateway': {},
	venmo: {},
	'pay-later': {},
	'ppcp-card-button-gateway': {},
	'ppcp-credit-card-gateway': {},
	'ppcp-axo-gateway': {},
	'ppcp-applepay': {},
	'ppcp-googlepay': {},
	'ppcp-bancontact': {},
	'ppcp-blik': {},
	'ppcp-eps': {},
	'ppcp-ideal': {},
	'ppcp-mybank': {},
	'ppcp-p24': {},
	'ppcp-trustly': {},
	'ppcp-multibanco': {},
	'ppcp-pay-upon-invoice-gateway': {},
	'ppcp-oxxo-gateway': {},
	paypalShowLogo: false,
	threeDSecure: 'no-3d-secure',
	fastlaneCardholderName: false,
	fastlaneDisplayWatermark: false,
} );

// Reducer logic.

const [ changeTransient, changePersistent ] = createReducerSetters(
	defaultTransient,
	defaultPersistent
);

const reducer = createReducer( defaultTransient, defaultPersistent, {
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
		cleanState.isReady = true;

		return cleanState;
	},

	[ ACTION_TYPES.HYDRATE ]: ( state, payload ) =>
		changePersistent( state, payload.data ),
} );

export default reducer;
