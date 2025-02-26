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
 * Hydrates the features data from the API.
 *
 * @return {Object} Action to dispatch.
 */
export function getFeatures() {
	return async ( { dispatch } ) => {
		try {
			const response = await apiFetch( { path: REST_PATH } );

			if ( response?.features ) {
				dispatch.setFeatures( response.features );
				dispatch.setIsReady( true );
			}
		} catch ( error ) {
			console.error( 'Error fetching features:', error );
		}
	};
}
