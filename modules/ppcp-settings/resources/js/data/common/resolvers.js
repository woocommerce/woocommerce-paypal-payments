/**
 * Resolvers: Handle asynchronous data fetching for the store.
 *
 * These functions update store state with data from external sources.
 * Each resolver corresponds to a specific selector (selector with same name must exist).
 * Resolvers are called automatically when selectors request unavailable data.
 *
 * @file
 */

import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import { REST_HYDRATE_PATH, REST_WEBHOOKS } from './constants';

/**
 * Retrieve settings from the site's REST API.
 */
export function persistentData() {
	return async ( { dispatch, registry } ) => {
		try {
			const [ result, webhooks ] = await Promise.all( [
				apiFetch( { path: REST_HYDRATE_PATH } ),
				apiFetch( { path: REST_WEBHOOKS } ),
			] );

			if ( result?.success && webhooks?.success && webhooks.data ) {
				result.webhooks = webhooks.data;
			}

			await dispatch.hydrate( result );
			await dispatch.setIsReady( true );
		} catch ( e ) {
			await registry
				.dispatch( 'core/notices' )
				.createErrorNotice(
					__(
						'Error retrieving plugin details.',
						'woocommerce-paypal-payments'
					)
				);
		}
	};
}
