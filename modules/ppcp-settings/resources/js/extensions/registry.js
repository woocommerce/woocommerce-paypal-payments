import { isValidSlot } from './slots';

/**
 * Internal registry storage.
 * Structure: { slotName: [ { component, priority, id }, ... ] }
 */
const registry = {};

/**
 * Register a component to render in a specific slot.
 *
 * @param {string}    slot      - The slot identifier (use SLOTS constants).
 * @param {Component} component - The React component to render.
 * @param {number}    priority  - Rendering priority (lower = earlier). Default: 10.
 * @param {?string}   id        - Optional unique identifier for this registration.
 */
export const registerSetting = (
	slot,
	component,
	priority = 10,
	id = null
) => {
	if ( ! isValidSlot( slot ) ) {
		console.warn(
			`[SettingsRegistry] Invalid slot name: "${ slot }". Check SLOTS constants.`
		);
		return;
	}

	if ( ! registry[ slot ] ) {
		registry[ slot ] = [];
	}

	// Generate ID if not provided
	const registrationId = id || `${ slot }-${ registry[ slot ].length }`;

	// Check for duplicate ID
	if ( registry[ slot ].some( ( item ) => item.id === registrationId ) ) {
		console.warn(
			`[SettingsRegistry] Duplicate registration ID: "${ registrationId }" in slot "${ slot }"`
		);
		return;
	}

	registry[ slot ].push( {
		component,
		priority,
		id: registrationId,
	} );

	// Sort by priority after each registration
	registry[ slot ].sort( ( a, b ) => a.priority - b.priority );
};

/**
 * Unregister a component from a slot.
 *
 * @param {string} slot - The slot identifier.
 * @param {string} id   - The registration ID to remove.
 */
export const unregisterSetting = ( slot, id ) => {
	if ( ! registry[ slot ] ) {
		return;
	}

	registry[ slot ] = registry[ slot ].filter( ( item ) => item.id !== id );
};

/**
 * Get all registered components for a slot.
 *
 * @param {string} slot - The slot identifier.
 * @return {Array} Array of registered components with metadata.
 */
export const getRegisteredSettings = ( slot ) => {
	return registry[ slot ] || [];
};

/**
 * Clear all registrations (mainly for testing).
 */
export const clearRegistry = () => {
	Object.keys( registry ).forEach( ( key ) => {
		delete registry[ key ];
	} );
};

/**
 * Get current registry state (for debugging).
 */
export const getRegistryState = () => {
	return { ...registry };
};
