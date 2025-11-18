/**
 * Settings Extension Registry Functions
 *
 * Provides functions to register components and extension stores
 * in the central Redux store.
 *
 * @file
 */

import { dispatch } from '@wordpress/data';
import { STORE_NAME } from './store';
import { isValidSlot } from './slots';

/**
 * Register a component to render in a specific slot.
 *
 * @param {string}               slot      - The slot identifier (use SLOTS constants).
 * @param {string}               id        - Unique identifier for this registration.
 * @param {Function | Component} component - The React component to render.
 * @param {number}               priority  - Rendering priority (lower = earlier). Default: 10.
 */
export const registerSetting = ( slot, id, component, priority = 10 ) => {
	if ( ! isValidSlot( slot ) ) {
		console.warn( `[SettingsRegistry] Invalid slot: "${ slot }"` );
		return;
	}

	dispatch( STORE_NAME ).registerSetting( slot, id, component, priority );
};

/**
 * Unregister a component from a slot.
 *
 * @param {string} slot - The slot identifier.
 * @param {string} id   - The registration ID to remove.
 */
export const unregisterSetting = ( slot, id ) => {
	dispatch( STORE_NAME ).unregisterSetting( slot, id );
};

/**
 * Register an extension store for central persistence management.
 *
 * Called automatically by createExtensionStore() - extensions don't call this directly.
 *
 * @param {string} storeName      - The Redux store name
 * @param {Object} config         - Store configuration
 * @param {string} config.key     - Unique key for activity tracking
 * @param {string} config.message - Activity message for persist operations
 * @param {Object} config.store   - Store interface with persist/refresh methods
 */
export const registerExtensionStore = ( storeName, config ) => {
	dispatch( STORE_NAME ).registerExtensionStore( storeName, config );
};
