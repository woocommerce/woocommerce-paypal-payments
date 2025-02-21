/**
 * Action Creators: Define functions to create action objects.
 *
 * These functions update state or trigger side effects (e.g., async operations).
 * Actions are categorized as Transient, Persistent, or Side effect.
 *
 * @file
 */

import apiFetch from '@wordpress/api-fetch';

import ACTION_TYPES from './action-types';
import {
	REST_COMPLETE_ONCLICK_PATH,
	REST_PATH,
	REST_PERSIST_PATH,
	REST_RESET_DISMISSED_TODOS_PATH,
} from './constants';

/**
 * Special. Resets all values in the store to initial defaults.
 *
 * @return {Object} The action.
 */
export const reset = () => ( {
	type: ACTION_TYPES.RESET,
} );

/**
 * Generic transient-data updater.
 *
 * @param {string} prop  Name of the property to update.
 * @param {any}    value The new value of the property.
 * @return {Object} The action.
 */
export const setTransient = ( prop, value ) => ( {
	type: ACTION_TYPES.SET_TRANSIENT,
	payload: { [ prop ]: value },
} );

/**
 * Generic persistent-data updater.
 *
 * @param {string} prop  Name of the property to update.
 * @param {any}    value The new value of the property.
 * @return {Object} The action.
 */
export const setPersistent = ( prop, value ) => ( {
	type: ACTION_TYPES.SET_PERSISTENT,
	payload: { [ prop ]: value },
} );

/**
 * Transient. Marks the store as "ready", i.e., fully initialized.
 *
 * @param {boolean} isReady Whether the store is ready
 * @return {Object} The action.
 */
export const setIsReady = ( isReady ) => setTransient( 'isReady', isReady );

export const setTodos = ( todos ) => ( {
	type: ACTION_TYPES.SET_TODOS,
	payload: todos,
} );

export const setDismissedTodos = ( dismissedTodos ) => ( {
	type: ACTION_TYPES.SET_DISMISSED_TODOS,
	payload: dismissedTodos,
} );

export const setCompletedTodos = ( completedTodos ) => ( {
	type: ACTION_TYPES.SET_COMPLETED_TODOS,
	payload: completedTodos,
} );

// Thunks

// TODO: Possibly, this should be a resolver?
export function fetchTodos() {
	return async () => {
		const response = await apiFetch( { path: REST_PATH } );
		return response?.data || [];
	};
}

/**
 * Thunk action creator. Triggers the persistence of store data to the server.
 *
 * @return {Function} The thunk function.
 */
export function persist() {
	return async ( { select } ) => {
		await apiFetch( {
			path: REST_PERSIST_PATH,
			method: 'POST',
			data: select.persistentData(),
		} );
	};
}

/**
 * Thunk action creator. Forces a data refresh from the REST API, replacing the current Redux values.
 *
 * @return {Function} The thunk function.
 */
export function refresh() {
	return ( { dispatch, select } ) => {
		dispatch.invalidateResolutionForStore();

		select.persistentData();
	};
}

export function resetDismissedTodos() {
	return async ( { dispatch } ) => {
		try {
			const result = await apiFetch( {
				path: REST_RESET_DISMISSED_TODOS_PATH,
				method: 'POST',
			} );

			if ( result && result.success ) {
				await dispatch.setDismissedTodos( [] );
			}

			return result;
		} catch ( e ) {
			return {
				success: false,
				error: e,
				message: e.message,
			};
		}
	};
}

export function completeOnClick( todoId ) {
	return async ( { select, dispatch } ) => {
		try {
			const result = await apiFetch( {
				path: REST_COMPLETE_ONCLICK_PATH,
				method: 'POST',
				data: { todoId },
			} );

			if ( result?.success ) {
				// Set transient completed state for visual feedback
				const completed = await select.getCompletedTodos();
				await dispatch.setCompletedTodos( [ ...completed, todoId ] );
			}

			return result;
		} catch ( e ) {
			return {
				success: false,
				error: e,
				message: e.message,
			};
		}
	};
}
