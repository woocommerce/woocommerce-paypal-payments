/**
 * Action Types: Define unique identifiers for actions across all store modules.
 *
 * @file
 */

export default {
	// Transient data.
	SET_TRANSIENT: 'ppcp/onboarding/SET_TRANSIENT',

	// Persistent data.
	SET_PERSISTENT: 'ppcp/onboarding/SET_PERSISTENT',
	RESET: 'ppcp/onboarding/RESET',
	HYDRATE: 'ppcp/onboarding/HYDRATE',

	// Gateway sync flag
	SYNC_GATEWAYS: 'ppcp/onboarding/SYNC_GATEWAYS',
	REFRESH_GATEWAYS: 'ppcp/onboarding/REFRESH_GATEWAYS',
};
