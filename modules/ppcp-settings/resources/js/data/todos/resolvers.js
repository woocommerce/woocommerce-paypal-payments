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
import { STORE_NAME, REST_PATH } from './constants';

export const resolvers = {
	*getTodos() {
		try {
			const response = yield apiFetch( { path: REST_PATH } );

			// Make sure we're accessing the correct part of the response
			const todos = response?.data || [];

			yield dispatch( STORE_NAME ).setTodos( todos );
			yield dispatch( STORE_NAME ).setIsReady( true );

		} catch ( e ) {
			console.error( 'Resolver error:', e );
			yield dispatch( STORE_NAME ).setIsReady( false );
			yield dispatch( 'core/notices' ).createErrorNotice(
				__( 'Error retrieving todos.', 'woocommerce-paypal-payments' )
			);
		}
	},
};
