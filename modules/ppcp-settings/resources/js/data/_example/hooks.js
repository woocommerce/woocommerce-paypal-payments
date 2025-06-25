/**
 * Hooks: Provide the main API for components to interact with the store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useMemo } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

import { createHooksForStore } from '../utils';
import { STORE_NAME } from './constants';

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

export const useStore = () => {
	const { select, dispatch, useTransient } = useStoreData();
	const { persist, refresh } = dispatch;
	const [ isReady ] = useTransient( 'isReady' );

	// Load persistent data from REST if not done yet.
	if ( ! isReady ) {
		select.persistentData();
	}

	return { persist, refresh, isReady };
};

// TODO: Replace with real hook.
export const useSampleValue = () => {
	const { usePersistent, select } = useStoreData();
	const [ sampleValue, setSampleValue ] = usePersistent( 'sampleValue' );

	return {
		sampleValue,
		setSampleValue,
		flags: select.flags(),
	};
};
