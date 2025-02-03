/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useDispatch } from '@wordpress/data';

import { createHooksForStore } from '../utils';
import { STORE_NAME } from './constants';

const useHooks = () => {
	const { useTransient, usePersistent } = createHooksForStore( STORE_NAME );
	const { persist } = useDispatch( STORE_NAME );

	// Read-only flags and derived state.
	// Nothing here yet.

	// Transient accessors.
	const [ isReady ] = useTransient( 'isReady' );

	// Persistent accessors.
	// TODO: Replace with real property.
	const [ sampleValue, setSampleValue ] = usePersistent( 'sampleValue' );

	return {
		persist,
		isReady,
		sampleValue,
		setSampleValue,
	};
};

export const useStore = () => {
	const { persist, isReady } = useHooks();
	return { persist, isReady };
};

// TODO: Replace with real hook.
export const useSampleValue = () => {
	const { sampleValue, setSampleValue } = useHooks();

	return {
		sampleValue,
		setSampleValue,
	};
};
