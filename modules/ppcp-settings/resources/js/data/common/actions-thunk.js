import apiFetch from '@wordpress/api-fetch';

import {
	REST_CONNECTION_URL_PATH,
	REST_DIRECT_AUTHENTICATION_PATH,
	REST_DISCONNECT_MERCHANT_PATH,
	REST_HYDRATE_MERCHANT_PATH,
	REST_OAUTH_AUTHENTICATION_PATH,
	REST_PERSIST_PATH,
	REST_REFRESH_FEATURES_PATH,
	REST_WEBHOOKS,
	REST_WEBHOOKS_SIMULATE,
} from './constants';

/**
 * Side effect. Saves the persistent details to the WP database.
 *
 * @return {Function} The thunk function.
 */
export function persist() {
	return async ( { select } ) => {
		await apiFetch( {
			path: REST_PERSIST_PATH,
			method: 'POST',
			data: select.persistentData(),
		} );
	};
}

/**
 * Thunk action creator. Forces a data refresh from the REST API, replacing the current Redux values.
 *
 * @return {Function} The thunk function.
 */
export function refresh() {
	return ( { dispatch, select } ) => {
		dispatch.invalidateResolutionForStore();

		select.persistentData();
	};
}

/**
 * Side effect. Fetches the ISU-login URL for an account.
 *
 * @param {string[]} [products=[]] Which products/features to display in the ISU popup.
 * @param {Object}   [options={}]  Options to customize the onboarding workflow.
 * @param            isSandbox     True if is sandbox, otherwise false.
 * @return {Function} The thunk function.
 */
export function onboardingUrl(
	products = [],
	options = {},
	isSandbox = false
) {
	return async () => {
		try {
			return apiFetch( {
				path: REST_CONNECTION_URL_PATH,
				method: 'POST',
				data: {
					useSandbox: isSandbox,
					products,
					options,
				},
			} );
		} catch ( e ) {
			return {
				success: false,
				error: e,
			};
		}
	};
}

/**
 * Side effect. Initiates a direct connection attempt using the provided client ID and secret.
 *
 * This action accepts parameters instead of fetching data from the Redux state because the
 * values (ID and secret) are not managed by a central redux store, but might come from private
 * component state.
 *
 * @param {string}  clientId     - AP client ID (always 80-characters, starting with "A").
 * @param {string}  clientSecret - API client secret.
 * @param {boolean} useSandbox   - Whether the credentials are for a sandbox account.
 * @return {Function} The thunk function.
 */
export function authenticateWithCredentials(
	clientId,
	clientSecret,
	useSandbox
) {
	return async () => {
		try {
			return await apiFetch( {
				path: REST_DIRECT_AUTHENTICATION_PATH,
				method: 'POST',
				data: {
					clientId,
					clientSecret,
					useSandbox,
				},
			} );
		} catch ( e ) {
			return {
				success: false,
				error: e,
			};
		}
	};
}

/**
 * Side effect. Completes the ISU login by authenticating the user via the one time sharedId and
 * authCode provided by PayPal.
 *
 * This action accepts parameters instead of fetching data from the Redux state because all
 * parameters are dynamically generated during the authentication process, and not managed by our
 * Redux store.
 *
 * @param {string}  sharedId   - OAuth client ID; called "sharedId" to prevent confusion with the
 *                             API client ID.
 * @param {string}  authCode   - OAuth authorization code provided during onboarding.
 * @param {boolean} useSandbox - Whether the credentials are for a sandbox account.
 * @return {Function} The thunk function.
 */
export function authenticateWithOAuth( sharedId, authCode, useSandbox ) {
	return async () => {
		try {
			return await apiFetch( {
				path: REST_OAUTH_AUTHENTICATION_PATH,
				method: 'POST',
				data: {
					sharedId,
					authCode,
					useSandbox,
				},
			} );
		} catch ( e ) {
			return {
				success: false,
				error: e,
			};
		}
	};
}

/**
 * Side effect. Checks webhook simulation.
 *
 * @param {boolean} fullReset When true, all plugin settings are reset to initial values.
 * @return {Function} The thunk function.
 */
export function disconnectMerchant( fullReset = false ) {
	return async () => {
		return await apiFetch( {
			path: REST_DISCONNECT_MERCHANT_PATH,
			method: 'POST',
			data: {
				reset: fullReset,
			},
		} );
	};
}

/**
 * Side effect. Clears and refreshes the merchant data via a REST request.
 *
 * @return {Function} The thunk function.
 */
export function refreshMerchantData() {
	return async ( { dispatch } ) => {
		try {
			await dispatch.resetMerchant();
			const result = await apiFetch( {
				path: REST_HYDRATE_MERCHANT_PATH,
			} );

			if ( result.success && result.merchant ) {
				dispatch.hydrate( result );
			}

			return result;
		} catch ( e ) {
			return {
				success: false,
				error: e,
			};
		}
	};
}

/**
 * Side effect.
 * Purges all feature status data via a REST request.
 * Refreshes the merchant data via a REST request.
 *
 * @return {Function} The thunk function.
 */
export function refreshFeatureStatuses() {
	return async ( { dispatch } ) => {
		try {
			const result = await apiFetch( {
				path: REST_REFRESH_FEATURES_PATH,
				method: 'POST',
			} );

			if ( result && result.success ) {
				// TODO: Review if we can get the updated feature details in the result.data
				// instead of doing a second refreshMerchantData() request.
				await dispatch.refreshMerchantData();
			}

			return result;
		} catch ( e ) {
			return {
				success: false,
				error: e,
				message: e.message,
			};
		}
	};
}

/**
 * Side effect
 * Refreshes subscribed webhooks via a REST request
 *
 * @return {Function} The thunk function.
 */
export function resubscribeWebhooks() {
	return async ( { dispatch } ) => {
		try {
			const result = await apiFetch( {
				method: 'POST',
				path: REST_WEBHOOKS,
			} );

			if ( result.success && result.merchant ) {
				dispatch.hydrate( result );
			}

			return result;
		} catch ( e ) {
			return {
				success: false,
				error: e,
			};
		}
	};
}

/**
 * Side effect. Starts webhook simulation.
 *
 * @return {Function} The thunk function.
 */
export function startWebhookSimulation() {
	return async () => {
		return await apiFetch( {
			method: 'POST',
			path: REST_WEBHOOKS_SIMULATE,
		} );
	};
}

/**
 * Side effect. Checks webhook simulation.
 *
 * @return {Function} The thunk function.
 */
export function checkWebhookSimulationState() {
	return async () => {
		return await apiFetch( {
			path: REST_WEBHOOKS_SIMULATE,
		} );
	};
}
