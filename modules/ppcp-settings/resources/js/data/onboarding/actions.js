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
 * Side effect. Triggers the persistence of onboarding data to the server.
 *
 * @return {Action} The action.
 */
export const persist = function* () {
	const data = yield select( STORE_NAME ).persistentData();

	yield { type: ACTION_TYPES.DO_PERSIST_DATA, data };
};
