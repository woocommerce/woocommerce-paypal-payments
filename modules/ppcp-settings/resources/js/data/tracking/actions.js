/**
 * Action Creators: Define functions to create action objects.
 *
 * These functions update state or trigger side effects (e.g., async operations).
 * Two main actions: updateSources and clearSources.
 *
 * @file
 */

import ACTION_TYPES from './action-types';

/**
 * Updates the source tracking information for a specific field.
 *
 * Records when and from where a field value was changed, enabling.
 * audit trails and debugging capabilities for state modifications.
 *
 * @param {string} storeName - Name of the store containing the field.
 * @param {string} fieldName - Name of the field being tracked.
 * @param {string} source    - Source identifier for the change (e.g., 'user', 'system').
 * @return {Object} Action object with type UPDATE_SOURCES and tracking payload.
 */
export const updateSources = ( storeName, fieldName, source ) => ( {
	type: ACTION_TYPES.UPDATE_SOURCES,
	payload: {
		storeName,
		fieldName,
		source,
		timestamp: Date.now(),
	},
} );

/**
 * Clears source tracking information for fields or stores.
 *
 * Can clear tracking for a specific field, all fields in a store,
 * or all tracking data.
 *
 * @param {string|null} storeName - Name of the store (optional, null clears all stores).
 * @param {string|null} fieldName - Name of the field (optional, null clears all fields in store).
 * @return {Object} Action object with appropriate clear type and payload.
 */
export const clearSources = ( storeName = null, fieldName = null ) => {
	if ( fieldName ) {
		return {
			type: ACTION_TYPES.CLEAR_FIELD_SOURCE,
			payload: { storeName, fieldName },
		};
	}
	return {
		type: ACTION_TYPES.CLEAR_SOURCES,
		payload: { storeName },
	};
};

/**
 * Resets all source tracking data.
 *
 * Clears all stored tracking information across all stores and fields,
 * returning the tracking store to its initial state.
 *
 * @return {Object} Action object with type RESET.
 */
export const reset = () => ( {
	type: ACTION_TYPES.RESET,
} );
