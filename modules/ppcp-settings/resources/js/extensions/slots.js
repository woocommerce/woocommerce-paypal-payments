/**
 * Define all available extension slots in the settings UI.
 *
 * Naming convention: COMPONENT_SECTION_LOCATION
 * - COMPONENT: The main component/page (e.g., PAYPAL_SETTINGS, EXPERT_SETTINGS)
 * - SECTION: Optional specific section within component
 * - LOCATION: Position hint (e.g. BEFORE, AFTER)
 */
export const SLOTS = {
	// PayPal Settings accordion slots
	PAYPAL_SETTINGS_AFTER: 'settings.paypal.after',

	// Expert Settings card slots
	EXPERT_SETTINGS_AFTER: 'settings.expert.after',

	// Common Settings card slots
	COMMON_SETTINGS_AFTER: 'settings.common.after',

	// Connection Status card slots
	CONNECTION_STATUS_AFTER: 'settings.connection.after',
};

/**
 * Helper to validate slot names.
 * Useful during development to catch typos.
 *
 * @param {string} slotName - Slot name to validate.
 */
export const isValidSlot = ( slotName ) => {
	return Object.values( SLOTS ).includes( slotName );
};
