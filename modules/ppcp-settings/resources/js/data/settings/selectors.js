/**
 * Selectors: Extract specific pieces of state from the store.
 *
 * These functions provide a consistent interface for accessing store data.
 * They allow components to retrieve data without knowing the store structure.
 *
 * @file
 */

/**
 * Empty frozen object used as fallback when state is undefined.
 *
 * @constant
 * @type {Object}
 */
const EMPTY_OBJ = Object.freeze( {} );

/**
 * Base selector that ensures a valid state object.
 *
 * @param {Object|undefined} state The current state
 * @return {Object} The state or empty object if undefined
 */
export const getState = ( state ) => state || EMPTY_OBJ;

/**
 * Retrieves persistent (saved) data from the store.
 *
 * @param {Object} state The current state
 * @return {Object} The persistent data or empty object if undefined
 */
export const persistentData = ( state ) => {
	return getState( state ).data || EMPTY_OBJ;
};

/**
 * Retrieves transient (temporary) data from the store.
 * Excludes persistent data stored in the 'data' property.
 *
 * @param {Object} state The current state
 * @return {Object} The transient state or empty object if undefined
 */
export const transientData = ( state ) => {
	const { data, ...transientState } = getState( state );
	return transientState || EMPTY_OBJ;
};
