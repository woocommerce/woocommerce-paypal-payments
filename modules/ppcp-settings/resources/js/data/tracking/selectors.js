/**
 * Selectors: Field source tracking store.
 *
 * Accessors for field source information.
 *
 * @file
 */

/**
 * Get field source for specific field.
 *
 * @param {Object} state     - Store state.
 * @param {string} storeName - Name of the store.
 * @param {string} fieldName - Name of the field.
 * @return {Object|null} Source information or null.
 */
export const getFieldSource = ( state, storeName, fieldName ) => {
	return state?.[ storeName ]?.[ fieldName ] || null;
};

/**
 * Get all field sources for a store.
 *
 * @param {Object} state     - Store state.
 * @param {string} storeName - Name of the store.
 * @return {Object} All field sources for the store.
 */
export const getStoreFieldSources = ( state, storeName ) => {
	return state?.[ storeName ] || {};
};

/**
 * Get all field sources across all stores.
 *
 * @param {Object} state - Store state.
 * @return {Object} All field sources.
 */
export const getAllFieldSources = ( state ) => {
	return state || {};
};

/**
 * Check if field is tracked.
 *
 * @param {Object} state     - Store state.
 * @param {string} storeName - Name of the store.
 * @param {string} fieldName - Name of the field.
 * @return {boolean} True if field is tracked.
 */
export const isFieldTracked = ( state, storeName, fieldName ) => {
	return !! getFieldSource( state, storeName, fieldName );
};
