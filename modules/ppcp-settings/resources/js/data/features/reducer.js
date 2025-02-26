/**
 * Reducer: Defines store structure and state updates for features module.
 *
 * Manages both transient (temporary) and persistent (saved) state.
 * The initial state must define all properties, as dynamic additions are not supported.
 *
 * @file
 */

import { createReducer, createReducerSetters } from '../utils';
import ACTION_TYPES from './action-types';

// Store structure.

/**
 * Transient: Values that are _not_ saved to the DB (like app lifecycle-flags).
 * These reset on page reload.
 */
const defaultTransient = Object.freeze( {
	isReady: false,
} );

/**
 * Persistent: Values that are loaded from and saved to the DB.
 * These represent the core features configuration.
 */
const defaultPersistent = Object.freeze( {
	features: [],
} );

// Reducer logic.

const [ changeTransient, changePersistent ] = createReducerSetters(
	defaultTransient,
	defaultPersistent
);

/**
 * Reducer implementation mapping actions to state updates.
 */
const reducer = createReducer( defaultTransient, defaultPersistent, {
	/**
	 * Updates temporary state values
	 *
	 * @param {Object} state   Current state
	 * @param {Object} payload Update payload
	 * @return {Object} Updated state
	 */
	[ ACTION_TYPES.SET_TRANSIENT ]: ( state, payload ) =>
		changeTransient( state, payload ),

	/**
	 * Updates features list
	 *
	 * @param {Object} state   Current state
	 * @param {Object} payload Update payload containing features array
	 * @return {Object} Updated state
	 */
	[ ACTION_TYPES.SET_FEATURES ]: ( state, payload ) => {
		return changePersistent( state, { features: payload } );
	},

	/**
	 * Initializes persistent state with data from the server
	 *
	 * @param {Object} state        Current state
	 * @param {Object} payload      Hydration payload containing server data
	 * @param {Object} payload.data The features data to hydrate
	 * @return {Object} Hydrated state
	 */
	[ ACTION_TYPES.HYDRATE ]: ( state, payload ) =>
		changePersistent( state, payload.data ),
} );

export default reducer;
