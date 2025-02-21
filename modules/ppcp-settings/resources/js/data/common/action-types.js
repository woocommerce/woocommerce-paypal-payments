/**
 * Action Types: Define unique identifiers for actions across all store modules.
 *
 * @file
 */

export default {
	// Transient data.
	SET_TRANSIENT: 'ppcp/common/SET_TRANSIENT',

	// Persistent data.
	SET_PERSISTENT: 'ppcp/common/SET_PERSISTENT',
	RESET: 'ppcp/common/RESET',
	HYDRATE: 'ppcp/common/HYDRATE',
	SET_MERCHANT: 'ppcp/common/SET_MERCHANT',
	RESET_MERCHANT: 'ppcp/common/RESET_MERCHANT',

	// Activity management (advanced solution that replaces the isBusy state).
	START_ACTIVITY: 'ppcp/common/START_ACTIVITY',
	STOP_ACTIVITY: 'ppcp/common/STOP_ACTIVITY',
};
