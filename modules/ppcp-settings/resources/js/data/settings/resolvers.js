/**
 * Resolvers: Handle asynchronous data fetching for the store.
 *
 * These functions update store state with data from external sources.
 * Each resolver corresponds to a specific selector (selector with same name must exist).
 * Resolvers are called automatically when selectors request unavailable data.
 *
 * @file
 */

import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';
import { STORE_NAME, REST_HYDRATE_PATH } from './constants';

export const resolvers = {
	/**
	 * Retrieve PayPal settings from the site's REST API.
	 * Hydrates the store with the retrieved data and marks it as ready.
	 *
	 * @generator
	 * @yield {Object} API fetch and dispatch actions
	 */
	*persistentData() {
		try {
			// Fetch settings data from REST API
			const result = yield apiFetch( {
				path: REST_HYDRATE_PATH,
				method: 'GET',
				headers: {
					'Content-Type': 'application/json',
					Accept: 'application/json',
				},
			} );

			// Update store with retrieved data
			yield dispatch( STORE_NAME ).hydrate( result );
			// Mark store as ready for use
			yield dispatch( STORE_NAME ).setIsReady( true );
		} catch ( e ) {
			// Log detailed error information for debugging
			console.error( 'Full error details:', {
				error: e,
				path: REST_HYDRATE_PATH,
				store: STORE_NAME,
			} );

			// Display user-friendly error notice
			yield dispatch( 'core/notices' ).createErrorNotice(
				__(
					'Error retrieving PayPal settings details.',
					'woocommerce-paypal-payments'
				)
			);
		}
	},
};
