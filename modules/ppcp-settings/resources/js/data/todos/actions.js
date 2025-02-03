/**
 * Action Creators: Define functions to create action objects.
 *
 * These functions update state or trigger side effects (e.g., async operations).
 * Actions are categorized as Transient, Persistent, or Side effect.
 *
 * @file
 */

import ACTION_TYPES from './action-types';

export const setIsReady = ( isReady ) => ( {
	type: ACTION_TYPES.SET_TRANSIENT,
	payload: { isReady },
} );

export const setTodos = ( todos ) => ( {
	type: ACTION_TYPES.SET_TODOS,
	payload: todos,
} );

export const fetchTodos = function* () {
	yield { type: ACTION_TYPES.DO_FETCH_TODOS };
};
