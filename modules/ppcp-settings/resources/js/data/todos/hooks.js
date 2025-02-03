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

const useTransient = ( key ) =>
	useSelect(
		( select ) => select( STORE_NAME ).transientData()?.[ key ],
		[ key ]
	);

export const useTodos = () => {
	const todos = useSelect(
		( select ) => select( STORE_NAME ).getTodos(),
		[]
	);
	const isReady = useTransient( 'isReady' );

	const { fetchTodos } = useDispatch( STORE_NAME );

	return {
		todos,
		isReady,
		fetchTodos,
	};
};
