import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';
import { NAMESPACE } from '../constants';
import { setOnboardingDetails } from './actions';

/**
 * Retrieve settings from the site's REST API.
 */
export function* getPersistentData() {
	const path = `${ NAMESPACE }/onboarding`;

	try {
		const result = yield apiFetch( { path } );
		yield setOnboardingDetails( result );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__(
				'Error retrieving onboarding details.',
				'woocommerce-paypal-payments'
			)
		);
	}
}
