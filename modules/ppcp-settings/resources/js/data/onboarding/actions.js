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
 * Transient. Marks the onboarding details as "ready", i.e., fully initialized.
 *
 * @param {boolean} isReady
 * @return {Action} The action.
 */
export const setIsReady = ( isReady ) => ( {
	type: ACTION_TYPES.SET_TRANSIENT,
	payload: { isReady },
} );

/**
 * Transient. Sets the "manualClientId" value.
 *
 * @param {string} manualClientId
 * @return {Action} The action.
 */
export const setManualClientId = ( manualClientId ) => ( {
	type: ACTION_TYPES.SET_TRANSIENT,
	payload: { manualClientId },
} );

/**
 * Transient. Sets the "manualClientSecret" value.
 *
 * @param {string} manualClientSecret
 * @return {Action} The action.
 */
export const setManualClientSecret = ( manualClientSecret ) => ( {
	type: ACTION_TYPES.SET_TRANSIENT,
	payload: { manualClientSecret },
} );

/**
 * Persistent.Set the "onboarding completed" flag which shows or hides the wizard.
 *
 * @param {boolean} completed
 * @return {Action} The action.
 */
export const setCompleted = ( completed ) => ( {
	type: ACTION_TYPES.SET_PERSISTENT,
	payload: { completed },
} );

/**
 * Persistent. Sets the onboarding wizard to a new step.
 *
 * @param {number} step
 * @return {Action} The action.
 */
export const setStep = ( step ) => ( {
	type: ACTION_TYPES.SET_PERSISTENT,
	payload: { step },
} );

/**
 * Persistent. Sets the "isCasualSeller" value.
 *
 * @param {boolean} isCasualSeller
 * @return {Action} The action.
 */
export const setIsCasualSeller = ( isCasualSeller ) => ( {
	type: ACTION_TYPES.SET_PERSISTENT,
	payload: { isCasualSeller },
} );

/**
 * Persistent. Sets the "areOptionalPaymentMethodsEnabled" value.
 *
 * @param {boolean} areOptionalPaymentMethodsEnabled
 * @return {Action} The action.
 */
export const setAreOptionalPaymentMethodsEnabled = (
	areOptionalPaymentMethodsEnabled
) => ( {
	type: ACTION_TYPES.SET_PERSISTENT,
	payload: { areOptionalPaymentMethodsEnabled },
} );

/**
 * Persistent. Sets the "products" array.
 *
 * @param {string[]} products
 * @return {Action} The action.
 */
export const setProducts = ( products ) => ( {
	type: ACTION_TYPES.SET_PERSISTENT,
	payload: { products },
} );

/**
 * Side effect. Triggers the persistence of onboarding data to the server.
 *
 * @return {Action} The action.
 */
export const persist = function* () {
	const data = yield select( STORE_NAME ).persistentData();

	yield { type: ACTION_TYPES.DO_PERSIST_DATA, data };
};
