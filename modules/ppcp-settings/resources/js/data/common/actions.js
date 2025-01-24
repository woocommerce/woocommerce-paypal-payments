/**
 * Action Creators: Define functions to create action objects.
 *
 * These functions update state or trigger side effects (e.g., async operations).
 * Actions are categorized as Transient, Persistent, or Side effect.
 *
 * @file
 */

import { select } from '@wordpress/data';

import ACTION_TYPES from './action-types';
import { STORE_NAME } from './constants';

/**
 * @typedef {Object} Action An action object that is handled by a reducer or control.
 * @property {string}  type    - The action type.
 * @property {Object?} payload - Optional payload for the action.
 */

/**
 * Special. Resets all values in the onboarding store to initial defaults.
 *
 * @return {Action} The action.
 */
export const reset = () => ( { type: ACTION_TYPES.RESET } );

/**
 * Persistent. Set the full onboarding details, usually during app initialization.
 *
 * @param {{data: {}, flags?: {}}} payload
 * @return {Action} The action.
 */
export const hydrate = ( payload ) => ( {
	type: ACTION_TYPES.HYDRATE,
	payload,
} );

/**
 * Generic transient-data updater.
 *
 * @param {string} prop  Name of the property to update.
 * @param {any}    value The new value of the property.
 * @return {Action} The action.
 */
export const setTransient = ( prop, value ) => ( {
	type: ACTION_TYPES.SET_TRANSIENT,
	payload: { [ prop ]: value },
} );

/**
 * Generic persistent-data updater.
 *
 * @param {string} prop  Name of the property to update.
 * @param {any}    value The new value of the property.
 * @return {Action} The action.
 */
export const setPersistent = ( prop, value ) => ( {
	type: ACTION_TYPES.SET_PERSISTENT,
	payload: { [ prop ]: value },
} );

/**
 * Transient. Marks the onboarding details as "ready", i.e., fully initialized.
 *
 * @param {boolean} isReady
 * @return {Action} The action.
 */
export const setIsReady = ( isReady ) => setTransient( 'isReady', isReady );

/**
 * Transient. Sets the active settings tab.
 *
 * @param {string} activeModal
 * @return {Action} The action.
 */
export const setActiveModal = ( activeModal ) =>
	setTransient( 'activeModal', activeModal );

/**
 * Transient (Activity): Marks the start of an async activity
 * Think of it as "setIsBusy(true)"
 *
 * @param {string}  id          Internal ID/key of the action, used to stop it again.
 * @param {?string} description Optional, description for logging/debugging
 * @return {?Action} The action.
 */
export const startActivity = ( id, description = null ) => {
	if ( ! id || 'string' !== typeof id ) {
		console.warn( 'Activity ID must be a non-empty string' );
		return null;
	}

	return {
		type: ACTION_TYPES.START_ACTIVITY,
		payload: { id, description },
	};
};

/**
 * Transient (Activity): Marks the end of an async activity.
 * Think of it as "setIsBusy(false)"
 *
 * @param {string} id Internal ID/key of the action, used to stop it again.
 * @return {Action} The action.
 */
export const stopActivity = ( id ) => ( {
	type: ACTION_TYPES.STOP_ACTIVITY,
	payload: { id },
} );

/**
 * Persistent. Sets the sandbox mode on or off.
 *
 * @param {boolean} useSandbox
 * @return {Action} The action.
 */
export const setSandboxMode = ( useSandbox ) =>
	setPersistent( 'useSandbox', useSandbox );

/**
 * Persistent. Toggles the "Manual Connection" mode on or off.
 *
 * @param {boolean} useManualConnection
 * @return {Action} The action.
 */
export const setManualConnectionMode = ( useManualConnection ) =>
	setPersistent( 'useManualConnection', useManualConnection );

/**
 * Side effect. Saves the persistent details to the WP database.
 *
 * @return {Action} The action.
 */
export const persist = function* () {
	const data = yield select( STORE_NAME ).persistentData();

	yield { type: ACTION_TYPES.DO_PERSIST_DATA, data };
};

/**
 * Side effect. Fetches the ISU-login URL for a sandbox account.
 *
 * @return {Action} The action.
 */
export const sandboxOnboardingUrl = function* () {
	return yield {
		type: ACTION_TYPES.DO_GENERATE_ONBOARDING_URL,
		useSandbox: true,
		products: [ 'EXPRESS_CHECKOUT' ],
	};
};

/**
 * Side effect. Fetches the ISU-login URL for a production account.
 *
 * @param {string[]} products Which products/features to display in the ISU popup.
 * @return {Action} The action.
 */
export const productionOnboardingUrl = function* ( products = [] ) {
	return yield {
		type: ACTION_TYPES.DO_GENERATE_ONBOARDING_URL,
		useSandbox: false,
		products,
	};
};

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
 * @return {Action} The action.
 */
export const authenticateWithCredentials = function* (
	clientId,
	clientSecret,
	useSandbox
) {
	return yield {
		type: ACTION_TYPES.DO_DIRECT_API_AUTHENTICATION,
		clientId,
		clientSecret,
		useSandbox,
	};
};

/**
 * Side effect. Completes the ISU login by authenticating the user via the one time sharedId and
 * authCode provided by PayPal.
 *
 * This action accepts parameters instead of fetching data from the Redux state because all
 * parameters are dynamically generated during the authentication process, and not managed by our
 * Redux store.
 *
 * @param {string}  sharedId   - OAuth client ID; called "sharedId" to prevent confusion with the API client ID.
 * @param {string}  authCode   - OAuth authorization code provided during onboarding.
 * @param {boolean} useSandbox - Whether the credentials are for a sandbox account.
 * @return {Action} The action.
 */
export const authenticateWithOAuth = function* (
	sharedId,
	authCode,
	useSandbox
) {
	return yield {
		type: ACTION_TYPES.DO_OAUTH_AUTHENTICATION,
		sharedId,
		authCode,
		useSandbox,
	};
};

/**
 * Side effect. Checks webhook simulation.
 *
 * @return {Action} The action.
 */
export const disconnectMerchant = function* () {
	return yield { type: ACTION_TYPES.DO_DISCONNECT_MERCHANT };
};

/**
 * Side effect. Clears and refreshes the merchant data via a REST request.
 *
 * @return {Action} The action.
 */
export const refreshMerchantData = function* () {
	const result = yield { type: ACTION_TYPES.DO_REFRESH_MERCHANT };

	if ( result.success && result.merchant ) {
		yield hydrate( result );
	}

	return result;
};

/**
 * Side effect.
 * Purges all feature status data via a REST request.
 * Refreshes the merchant data via a REST request.
 *
 * @return {Action} The action.
 */
export const refreshFeatureStatuses = function* () {
	const result = yield { type: ACTION_TYPES.DO_REFRESH_FEATURES };

	if ( result && result.success ) {
		// TODO: Review if we can get the updated feature details in the result.data instead of
		//       doing a second refreshMerchantData() request.
		yield refreshMerchantData();
	}

	return result;
};

/**
 * Persistent. Changes the "webhooks" value.
 *
 * @param {string} webhooks
 * @return {Action} The action.
 */
export const setWebhooks = ( webhooks ) => ( {
	type: ACTION_TYPES.SET_PERSISTENT,
	payload: { webhooks },
} );

/**
 * Side effect
 * Refreshes subscribed webhooks via a REST request
 *
 * @return {Action} The action.
 */
export const resubscribeWebhooks = function* () {
	const result = yield { type: ACTION_TYPES.DO_RESUBSCRIBE_WEBHOOKS };

	if ( result && result.success ) {
		yield hydrate( result );
	}

	return result;
};

/**
 * Side effect. Starts webhook simulation.
 *
 * @return {Action} The action.
 */
export const startWebhookSimulation = function* () {
	return yield { type: ACTION_TYPES.DO_START_WEBHOOK_SIMULATION };
};

/**
 * Side effect. Checks webhook simulation.
 *
 * @return {Action} The action.
 */
export const checkWebhookSimulationState = function* () {
	return yield { type: ACTION_TYPES.DO_CHECK_WEBHOOK_SIMULATION };
};
