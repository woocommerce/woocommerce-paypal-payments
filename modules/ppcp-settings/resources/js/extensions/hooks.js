/**
 * Extension Registry Hooks
 *
 * React hooks for accessing registered components and extension stores.
 *
 * @file
 */

import { useSelect } from '@wordpress/data';
import { STORE_NAME } from './store';

/**
 * React hook to get registered settings for a slot.
 *
 * @param {string} slot - The slot identifier.
 * @return {Array} Array of registered components for the slot.
 */
export const useRegisteredSettings = ( slot ) => {
	return useSelect(
		( select ) => select( STORE_NAME ).getRegisteredSettings( slot ),
		[ slot ]
	);
};

/**
 * React hook to retrieve all registered extension stores.
 *
 * Used by useStoreManager to include extension stores in persistAll/refreshAll.
 * Returns stores in the format expected by useStoreManager.
 *
 * @return {Array<{key: string, message: string, store: {persist: Function, refresh: Function}}>} List of extension's Redux stores.
 */
export const useExtensionStores = () => {
	return useSelect(
		( select ) => select( STORE_NAME ).getExtensionStores(),
		[]
	);
};
