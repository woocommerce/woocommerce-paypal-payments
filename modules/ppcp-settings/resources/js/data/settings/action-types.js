/**
 * Settings action types
 *
 * Defines the constants used for dispatching actions in the settings store.
 * Each constant represents a unique action type that can be handled by reducers.
 *
 * @file
 */

export default {
	/**
	 * Represents setting transient (temporary) state data.
	 * These values are not persisted and will reset on page reload.
	 */
	SET_TRANSIENT: 'ppcp/settings/SET_TRANSIENT',

	/**
	 * Represents setting persistent state data.
	 * These values are meant to be saved to the server and persist between page loads.
	 */
	SET_PERSISTENT: 'ppcp/settings/SET_PERSISTENT',

	/**
	 * Resets the store state to its initial values.
	 * Used when needing to clear all settings data.
	 */
	RESET: 'ppcp/settings/RESET',

	/**
	 * Initializes the store with data, typically used during store initialization
	 * to set up the initial state with data from the server.
	 */
	HYDRATE: 'ppcp/settings/HYDRATE',
};
