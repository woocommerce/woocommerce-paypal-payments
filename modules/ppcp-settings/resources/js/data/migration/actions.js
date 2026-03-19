/**
 * Migration Actions: Side-effect functions for settings migration.
 *
 * These functions trigger server-side operations via the REST API.
 *
 * @file
 */

import apiFetch from '@wordpress/api-fetch';

const REST_ACDC_MIGRATION_PATH = '/wc/v3/wc_paypal/migrate-to-acdc';

/**
 * Side effect. Triggers the migration from BCDC to ACDC on the server.
 *
 * Disables the standard card button gateway and enables advanced card processing.
 * This operation is irreversible.
 *
 * @return {Promise} Resolves when the migration is complete.
 */
export const migrateToAcdc = () =>
	apiFetch( {
		path: REST_ACDC_MIGRATION_PATH,
		method: 'POST',
	} );
