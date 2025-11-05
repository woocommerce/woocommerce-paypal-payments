import { useMemo } from '@wordpress/element';
import { getRegisteredSettings } from './registry';

/**
 * React hook to get registered settings for a slot.
 *
 * @param {string} slot - The slot identifier.
 * @return {Array} Array of registered components.
 */
export const useRegisteredSettings = ( slot ) => {
	// Use useMemo to avoid recalculating on every render
	// Since registrations happen during initialization, this is stable
	return useMemo( () => getRegisteredSettings( slot ), [ slot ] );
};
