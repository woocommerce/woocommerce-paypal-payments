import { useSelect } from '@wordpress/data';
import { STORE_NAME } from './store';

/**
 * React hook to get registered settings for a slot.
 *
 * @param {string} slot - The slot identifier.
 * @return {Array} Array of registered components.
 */
export const useRegisteredSettings = ( slot ) => {
	return useSelect(
		( select ) => select( STORE_NAME ).getRegisteredSettings( slot ),
		[ slot ]
	);
};
