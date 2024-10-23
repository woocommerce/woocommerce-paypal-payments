import { select } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import ACTION_TYPES from './action-types';
import { NAMESPACE, STORE_NAME } from '../constants';

/**
 * Persistent. Set the full onboarding details, usually during app initialization.
 *
 * @param {Object} payload
 * @return {{type: string, payload}} The action.
 */
export const updateOnboardingDetails = ( payload ) => {
	return {
		type: ACTION_TYPES.SET_ONBOARDING_DETAILS,
		payload,
	};
};

/**
 * Persistent. Sets the onboarding wizard to a new step.
 *
 * @param {number} step
 * @return {{type: string, step}} An action.
 */
export const setOnboardingStep = ( step ) => {
	return {
		type: ACTION_TYPES.SET_ONBOARDING_STEP,
		step,
	};
};

/**
 * Persistent. Sets the sandbox mode on or off.
 *
 * @param {boolean} sandboxMode
 * @return {{type: string, useSandbox}} An action.
 */
export const setSandboxMode = ( sandboxMode ) => {
	return {
		type: ACTION_TYPES.SET_SANDBOX_MODE,
		useSandbox: sandboxMode,
	};
};

/**
 * Persistent. Toggles the "Manual Connection" mode on or off.
 *
 * @param {boolean} manualConnectionMode
 * @return {{type: string, useManualConnection}} An action.
 */
export const setManualConnectionMode = ( manualConnectionMode ) => {
	return {
		type: ACTION_TYPES.SET_MANUAL_CONNECTION_MODE,
		useManualConnection: manualConnectionMode,
	};
};

/**
 * Non-persistent. Changes the "saving" flag.
 *
 * @param {boolean} isSaving
 * @return {{type: string, isSaving}} The action.
 */
export const updateIsSaving = ( isSaving ) => {
	return {
		type: ACTION_TYPES.SET_IS_SAVING_ONBOARDING_DETAILS,
		isSaving,
	};
};

/**
 * Saves the persistent details to the WP database.
 *
 * @return {any} A generator function that handles the saving process.
 */
export function* persist() {
	let error = null;

	try {
		const path = `${ NAMESPACE }/onboarding`;
		const data = select( STORE_NAME ).getOnboardingData();

		yield updateIsSaving( true );

		yield apiFetch( {
			path,
			method: 'post',
			data,
		} );
	} catch ( e ) {
		error = e;
		console.error( 'Error saving progress.', e );
	} finally {
		yield updateIsSaving( false );
	}

	return error === null;
}
