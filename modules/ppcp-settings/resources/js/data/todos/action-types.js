/**
 * Action Types: Define unique identifiers for actions across all store modules.
 *
 * @file
 */

export default {
	/**
	 * Resets the store state to its initial values.
	 * Used when needing to clear all store data.
	 */
	RESET: 'ppcp/todos/RESET',

	// Transient data
	SET_TRANSIENT: 'ppcp/todos/SET_TRANSIENT',
	SET_COMPLETED_TODOS: 'ppcp/todos/SET_COMPLETED_TODOS',

	// Persistent data
	SET_TODOS: 'ppcp/todos/SET_TODOS',
	SET_DISMISSED_TODOS: 'ppcp/todos/SET_DISMISSED_TODOS',
	RESET_DISMISSED_TODOS: 'ppcp/todos/RESET_DISMISSED_TODOS',
};
