/**
 * Action Types: Define unique identifiers for actions across all store modules.
 *
 * @file
 */

export default {
	// Transient data.
	SET_TRANSIENT: 'PAY_LATER_MESSAGING:SET_TRANSIENT',

	// Persistent data.
	SET_PERSISTENT: 'PAY_LATER_MESSAGING:SET_PERSISTENT',
	RESET: 'PAY_LATER_MESSAGING:RESET',
	HYDRATE: 'PAY_LATER_MESSAGING:HYDRATE',

	// Controls - always start with "DO_".
	DO_PERSIST_DATA: 'PAY_LATER_MESSAGING:DO_PERSIST_DATA',
};
