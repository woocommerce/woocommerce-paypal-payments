/**
 * Action Creators: Define functions to create action objects.
 *
 * These functions update state or trigger side effects (e.g., async operations).
 * Actions are categorized as Transient, Persistent, or Side effect.
 *
 * @file
 */

import apiFetch from '@wordpress/api-fetch';

import ACTION_TYPES from './action-types';
import { REST_PERSIST_PATH } from './constants';

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
 * Thunk action creator. Triggers the persistence of onboarding data to the server.
 *
 * @return {Function} The thunk function.
 */
export function persist() {
	return async ( { select } ) => {
		try {
			await apiFetch( {
				path: REST_PERSIST_PATH,
				method: 'POST',
				data: select.persistentData(),
			} );
		} catch ( e ) {
			// We catch errors here, as the onboarding module is not handled by the persistAll hook.
			console.error( 'Error saving progress.', e );
		}
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
 * Persistent. Updates the gateway synced status.
 *
 * @param {boolean} synced The sync status to set
 * @return {Action} The action.
 */
export const updateGatewaysSynced = ( synced = true ) =>
	setPersistent( 'gatewaysSynced', synced );

/**
 * Persistent. Updates the gateway refreshed status.
 *
 * @param {boolean} refreshed The refreshed status to set
 * @return {Action} The action.
 */
export const updateGatewaysRefreshed = ( refreshed = true ) =>
	setPersistent( 'gatewaysRefreshed', refreshed );

/**
 * Action creator to sync payment gateways.
 * This will both update the state and persist it.
 *
 * @return {Function} The thunk function.
 */
export function syncGateways() {
	return async ( { dispatch } ) => {
		dispatch( setPersistent( 'gatewaysSynced', true ) );
		await dispatch.persist();
		return { success: true };
	};
}

export function refreshGateways() {
	return async ( { dispatch } ) => {
		dispatch( setPersistent( 'gatewaysRefreshed', true ) );
		await dispatch.persist();
		return { success: true };
	};
}
