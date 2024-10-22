import { dispatch, select } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
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
 * Non-persistent. Changes the "saving" flag.
 *
 * @param {boolean} isSaving
 * @return {{type: string, isSaving}} The action.
 */
export function updateIsSavingOnboardingDetails( isSaving ) {
	return {
		type: ACTION_TYPES.SET_IS_SAVING_ONBOARDING_DETAILS,
		isSaving,
	};
}

/**
 * Saves the persistent details to the WP database.
 *
 * @return {Generator<any>} A generator function that handles the saving process.
 */
export function* saveOnboardingDetails() {
	let error = null;

	try {
		const settings = select( STORE_NAME ).getOnboardingDetails();

		yield updateIsSavingOnboardingDetails( true );

		yield apiFetch( {
			path: `${ NAMESPACE }/onboarding`,
			method: 'POST',
			data: settings,
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
		yield updateIsSavingOnboardingDetails( false );
	}

	return error === null;
}
