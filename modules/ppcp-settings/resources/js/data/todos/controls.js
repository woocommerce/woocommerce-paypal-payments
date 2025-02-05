/**
 * Controls: Implement side effects, typically asynchronous operations.
 *
 * Controls use ACTION_TYPES keys as identifiers.
 * They are triggered by corresponding actions and handle external interactions.
 *
 * @file
 */

import apiFetch from '@wordpress/api-fetch';
import {
	REST_PATH,
	REST_PERSIST_PATH,
	REST_RESET_DISMISSED_TODOS_PATH,
	REST_COMPLETE_ONCLICK_PATH,
} from './constants';
import ACTION_TYPES from './action-types';

export const controls = {
	async [ ACTION_TYPES.DO_FETCH_TODOS ]() {
		const response = await apiFetch( {
			path: REST_PATH,
			method: 'GET',
		} );
		return response?.data || [];
	},
	async [ ACTION_TYPES.DO_PERSIST_DATA ]( { data } ) {
		return await apiFetch( {
			path: REST_PERSIST_PATH,
			method: 'POST',
			data,
		} );
	},
	async [ ACTION_TYPES.DO_RESET_DISMISSED_TODOS ]() {
		try {
			return await apiFetch( {
				path: REST_RESET_DISMISSED_TODOS_PATH,
				method: 'POST',
			} );
		} catch ( e ) {
			return {
				success: false,
				error: e,
				message: e.message,
			};
		}
	},
	async [ ACTION_TYPES.DO_COMPLETE_ONCLICK ]( { todoId } ) {
		try {
			const response = await apiFetch( {
				path: REST_COMPLETE_ONCLICK_PATH,
				method: 'POST',
				data: { todoId },
			} );

			return response;
		} catch ( e ) {
			return {
				success: false,
				error: e,
				message: e.message,
			};
		}
	},
};
