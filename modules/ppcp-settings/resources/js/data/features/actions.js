/**
 * Action Creators: Define functions to create action objects.
 *
 * These functions update state or trigger side effects (e.g., async operations).
 * Actions are categorized as Transient or Side effect.
 *
 * @file
 */

import apiFetch from '@wordpress/api-fetch';
import ACTION_TYPES from './action-types';
import { REST_PATH } from './constants';

/**
 * @typedef {Object} Action An action object that is handled by a reducer or control.
 * @property {string}  type    - The action type.
 * @property {Object?} payload - Optional payload for the action.
 */

/**
 * Set the full store details during app initialization.
 *
 * @param {{data: {}, flags?: {}}} payload
 * @return {Action} The action.
 */
export const hydrate = ( payload ) => ( {
	type: ACTION_TYPES.HYDRATE,
	payload,
} );

/**
 * Generic transient-data updater.
 *
 * @param {string} prop  Name of the property to update.
 * @param {any}    value The new value of the property.
 * @return {Action} The action.
 */
export const setTransient = ( prop, value ) => ( {
	type: ACTION_TYPES.SET_TRANSIENT,
	payload: { [ prop ]: value },
} );

/**
 * Transient. Marks the store as "ready", i.e., fully initialized.
 *
 * @param {boolean} isReady
 * @return {Action} The action.
 */
export const setIsReady = ( isReady ) => setTransient( 'isReady', isReady );

/**
 * Sets the features in the store.
 *
 * @param {Array} features The features to set.
 * @return {Action} The action.
 */
export const setFeatures = ( features ) => ( {
	type: ACTION_TYPES.SET_FEATURES,
	payload: features,
} );

/**
 * Fetches features from the server.
 *
 * @return {Promise<Array>} The features data.
 */
export const fetchFeatures = async () => {
	try {
		const response = await apiFetch( { path: REST_PATH } );
		if ( response?.data ) {
			return {
				success: true,
				features: response.data.features,
			};
		}
		return {
			success: false,
			features: [],
		};
	} catch ( e ) {
		return {
			success: false,
			error: e,
			message: e.message,
		};
	}
};
