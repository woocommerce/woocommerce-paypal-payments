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

export const controls = {
	async [ ACTION_TYPES.DO_PERSIST_DATA ]( { data } ) {
		return await apiFetch( {
			path: REST_PERSIST_PATH,
			method: 'POST',
			data,
		} );
	},
};
