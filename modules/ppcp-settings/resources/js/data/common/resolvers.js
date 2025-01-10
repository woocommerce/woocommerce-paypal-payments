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

import { STORE_NAME, REST_HYDRATE_PATH, REST_WEBHOOKS } from './constants';

export const resolvers = {
	/**
	 * Retrieve settings from the site's REST API.
	 */
	*persistentData() {
		try {
			const result = yield apiFetch( { path: REST_HYDRATE_PATH } );
			const webhooks = yield apiFetch( { path: REST_WEBHOOKS } );

			if ( webhooks.success && webhooks.data ) {
				result.webhooks = webhooks.data;
			}

			yield dispatch( STORE_NAME ).hydrate( result );
			yield dispatch( STORE_NAME ).setIsReady( true );
		} catch ( e ) {
			yield dispatch( 'core/notices' ).createErrorNotice(
				__(
					'Error retrieving plugin details.',
					'woocommerce-paypal-payments'
				)
			);
		}
	},
};
