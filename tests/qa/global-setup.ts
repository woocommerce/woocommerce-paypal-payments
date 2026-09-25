/**
 * External dependencies
 */
import { FullConfig } from '@playwright/test';
import { restLogin, guestStorageState } from '@inpsyde/playwright-utils/build';

async function globalSetup( config: FullConfig ) {
	const projectUse = config.projects[ 0 ].use;
	// Playwright also accepts per-origin credential arrays; this suite configures a single set.
	const httpCredentials = Array.isArray( projectUse.httpCredentials )
		? projectUse.httpCredentials[ 0 ]
		: projectUse.httpCredentials;

	await restLogin( {
		baseURL: projectUse.baseURL,
		storageStatePath: String( projectUse.storageState ),
		httpCredentials,
		user: {
			// @ts-ignore
			username: process.env.WP_USERNAME,
			// @ts-ignore
			password: process.env.WP_PASSWORD,
		},
	} );

	await guestStorageState( {
		baseURL: projectUse.baseURL,
		httpCredentials,
		storageStatePath: `${ process.env.STORAGE_STATE_PATH }/guest.json`,
	} );
}

export default globalSetup;
