/**
 * Selectors: Extract specific pieces of state from the store.
 *
 * These functions provide a consistent interface for accessing store data.
 * They allow components to retrieve data without knowing the store structure.
 *
 * @file
 */

const EMPTY_OBJ = Object.freeze( {} );
const EMPTY_ARR = Object.freeze( [] );

const getState = ( state ) => state || EMPTY_OBJ;
const getArray = ( value ) => {
	if ( Array.isArray( value ) ) {
		return value;
	}
	if ( value ) {
		return Object.values( value );
	}
	return EMPTY_ARR;
};

// TODO: Implement a persistentData resolver!
export const persistentData = ( state ) => {
	return getState( state ).data || EMPTY_OBJ;
};

export const transientData = ( state ) => {
	const { data, ...transientState } = getState( state );
	return transientState || EMPTY_OBJ;
};

export const getTodos = ( state ) => {
	const todos = state?.todos || persistentData( state ).todos;
	return getArray( todos );
};

export const getDismissedTodos = ( state ) => {
	const dismissed =
		state?.dismissedTodos || persistentData( state ).dismissedTodos;
	return getArray( dismissed );
};

export const getCompletedTodos = ( state ) => {
	return getArray( state?.completedTodos ); // Only look at root state, not persistent data
};
