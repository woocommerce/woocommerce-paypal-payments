/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from './constants';
import { createHooksForStore } from '../utils';
import { useMemo } from '@wordpress/element';

/**
 * Single source of truth for access Redux details.
 *
 * This hook returns a stable API to access actions, selectors and special hooks to generate
 * getter- and setters for transient or persistent properties.
 *
 * @return {{select, dispatch, useTransient, usePersistent}} Store data API.
 */
const useStoreData = () => {
	const select = useSelect( ( selectors ) => selectors( STORE_NAME ), [] );
	const dispatch = useDispatch( STORE_NAME );
	const { useTransient, usePersistent } = createHooksForStore( STORE_NAME );

	return useMemo(
		() => ( {
			select,
			dispatch,
			useTransient,
			usePersistent,
		} ),
		[ select, dispatch, useTransient, usePersistent ]
	);
};

const useHooks = () => {
	const { dispatch, select } = useStoreData();
	const { fetchTodos, setDismissedTodos, setCompletedTodos } = dispatch;

	// Get todos data from store
	const todos = select.getTodos();
	const dismissedTodos = select.getDismissedTodos();
	const completedTodos = select.getCompletedTodos();

	const dismissedSet = new Set( dismissedTodos );

	const dismissTodo = async ( todoId ) => {
		if ( ! dismissedSet.has( todoId ) ) {
			const newDismissedTodos = [ ...dismissedTodos, todoId ];
			await setDismissedTodos( newDismissedTodos );
		}
	};

	const setTodoCompleted = async ( todoId, isCompleted ) => {
		let newCompletedTodos;
		if ( isCompleted ) {
			newCompletedTodos = [ ...completedTodos, todoId ];
		} else {
			newCompletedTodos = completedTodos.filter(
				( id ) => id !== todoId
			);
		}
		await setCompletedTodos( newCompletedTodos );
	};

	const filteredTodos = todos.filter(
		( todo ) => ! dismissedSet.has( todo.id )
	);

	return {
		todos: filteredTodos,
		dismissedTodos,
		completedTodos,
		fetchTodos,
		dismissTodo,
		setTodoCompleted,
	};
};

export const useStore = () => {
	const { select, dispatch, useTransient } = useStoreData();
	const { persist, refresh } = dispatch;
	const [ isReady ] = useTransient( 'isReady' );

	// Load persistent data from REST if not done yet.
	if ( ! isReady ) {
		select.getTodos();
	}

	return { persist, refresh, isReady };
};

export const useTodos = () => {
	const { todos, fetchTodos, dismissTodo, setTodoCompleted } = useHooks();
	return { todos, fetchTodos, dismissTodo, setTodoCompleted };
};

export const useDismissedTodos = () => {
	const { dismissedTodos } = useHooks();
	return { dismissedTodos };
};

export const useCompletedTodos = () => {
	const { completedTodos } = useHooks();
	return { completedTodos };
};
