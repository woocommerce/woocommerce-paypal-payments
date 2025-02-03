/**
 * Reducer: Defines store structure and state updates for todos module.
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
 * These represent the core todos configuration.
 */
const defaultPersistent = Object.freeze( {
	todos: [],
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
	 * Updates todos list
	 *
	 * @param {Object} state   Current state
	 * @param {Object} payload Update payload
	 * @return {Object} Updated state
	 */
	[ ACTION_TYPES.SET_TODOS ]: ( state, payload ) => {
		return changePersistent( state, { todos: payload } );
	},

	/**
	 * Resets state to defaults while maintaining initialization status
	 *
	 * @param {Object} state Current state
	 * @return {Object} Reset state
	 */
	[ ACTION_TYPES.RESET ]: ( state ) => {
		const cleanState = changeTransient(
			changePersistent( state, defaultPersistent ),
			defaultTransient
		);
		cleanState.isReady = true; // Keep initialization flag
		return cleanState;
	},

	/**
	 * Initializes persistent state with data from the server
	 *
	 * @param {Object} state        Current state
	 * @param {Object} payload      Hydration payload containing server data
	 * @param {Object} payload.data The todos data to hydrate
	 * @return {Object} Hydrated state
	 */
	[ ACTION_TYPES.HYDRATE ]: ( state, payload ) =>
		changePersistent( state, payload.data ),
} );

export default reducer;
