/**
 * Controls: Implement side effects, typically asynchronous operations.
 *
 * Controls use ACTION_TYPES keys as identifiers.
 * They are triggered by corresponding actions and handle external interactions.
 *
 * @file
 */

import apiFetch from '@wordpress/api-fetch';
import { REST_PERSIST_PATH } from './constants';
import ACTION_TYPES from './action-types';

/**
 * Control handlers for settings store actions.
 * Each handler maps to an ACTION_TYPE and performs the corresponding async operation.
 */
export const controls = {
	/**
	 * Persists settings data to the server via REST API.
	 * Triggered by the DO_PERSIST_DATA action to save settings changes.
	 *
	 * @param {Object} action      The action object
	 * @param {Object} action.data The settings data to persist
	 * @return {Promise<Object>} The API response
	 */
	async [ ACTION_TYPES.DO_PERSIST_DATA ]( { data } ) {
		return await apiFetch( {
			path: REST_PERSIST_PATH,
			method: 'POST',
			data,
		} );
	},
};
