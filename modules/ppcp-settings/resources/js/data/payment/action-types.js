/**
 * Action Types: Define unique identifiers for actions across all store modules.
 *
 * @file
 */

export default {
	// Transient data.
	SET_TRANSIENT: 'PAYMENT:SET_TRANSIENT',

	// Persistent data.
	SET_PERSISTENT: 'PAYMENT:SET_PERSISTENT',
	RESET: 'PAYMENT:RESET',
	HYDRATE: 'PAYMENT:HYDRATE',

	// Controls - always start with "DO_".
	DO_PERSIST_DATA: 'PAYMENT:DO_PERSIST_DATA',
};
