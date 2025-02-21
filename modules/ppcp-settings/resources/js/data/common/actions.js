/**
 * Action Creators: Define functions to create action objects.
 *
 * These functions update state or trigger side effects (e.g., async operations).
 * Actions are categorized as Transient, Persistent, or Side effect.
 *
 * @file
 */

import ACTION_TYPES from './action-types';

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
 * Transient. Sets the active settings highlight.
 *
 * @param {string} activeHighlight
 * @return {Action} The action.
 */
export const setActiveHighlight = ( activeHighlight ) =>
	setTransient( 'activeHighlight', activeHighlight );

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
 * Persistent. Changes the "webhooks" value.
 *
 * @param {string} webhooks
 * @return {Action} The action.
 */
export const setWebhooks = ( webhooks ) =>
	setPersistent( 'webhooks', webhooks );

/**
 * Replace merchant details in the store.
 *
 * @param {Object} merchant - The new merchant details.
 * @return {Action} The action.
 */
export const setMerchant = ( merchant ) => ( {
	type: ACTION_TYPES.SET_MERCHANT,
	payload: { merchant },
} );

/**
 * Reset merchant details in the store.
 *
 * @return {Action} The action.
 */
export const resetMerchant = () => ( { type: ACTION_TYPES.RESET_MERCHANT } );

// Activity control - see useBusyState() hook.

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
