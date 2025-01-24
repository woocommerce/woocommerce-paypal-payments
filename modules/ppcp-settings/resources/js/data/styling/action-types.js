/**
 * Action Types: Define unique identifiers for actions across all store modules.
 *
 * @file
 */

export default {
	// Transient data.
	SET_TRANSIENT: 'STYLE:SET_TRANSIENT',

	// Persistent data.
	SET_PERSISTENT: 'STYLE:SET_PERSISTENT',
	RESET: 'STYLE:RESET',
	HYDRATE: 'STYLE:HYDRATE',

	// Controls - always start with "DO_".
	DO_PERSIST_DATA: 'STYLE:DO_PERSIST_DATA',
};
