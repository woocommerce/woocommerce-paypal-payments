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

import { REST_PATH } from './constants';

/**
 * Retrieve settings from the site's REST API.
 */
export function getTodos() {
	return async ( { dispatch, registry } ) => {
		try {
			const response = await apiFetch( { path: REST_PATH } );

			const { todos = [], dismissedTodos = [] } = response?.data || {};

			await dispatch.setTodos( todos );
			await dispatch.setDismissedTodos( dismissedTodos );
			await dispatch.setIsReady( true );
		} catch ( e ) {
			await registry
				.dispatch( 'core/notices' )
				.createErrorNotice(
					__(
						'Error retrieving todos.',
						'woocommerce-paypal-payments'
					)
				);
		}
	};
}
