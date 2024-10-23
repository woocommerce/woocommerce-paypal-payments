import { dispatch, select } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { __ } from '@wordpress/i18n';
import ACTION_TYPES from './action-types';
import { NAMESPACE, STORE_NAME } from '../constants';

/**
 * Persistent. Set the full onboarding details, usually during app initialization.
 *
 * @param {Object} payload
 * @return {{payload, type: string}} The action.
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
 * @return {Generator<any>} A generator function that handles the saving process.
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

		yield dispatch( 'core/notices' ).createSuccessNotice(
			__( 'Progress saved.', 'woocommerce-paypal-payments' )
		);
	} catch ( e ) {
		error = e;
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error saving progress.', 'woocommerce-paypal-payments' )
		);
	} finally {
		yield updateIsSaving( false );
	}

	return error === null;
}
