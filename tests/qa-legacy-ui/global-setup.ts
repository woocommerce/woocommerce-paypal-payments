/**
 * External dependencies
 */
import { FullConfig } from '@playwright/test';
import { restLogin, guestStorageState } from '@inpsyde/playwright-utils/build';
import { execFileSync  } from 'node:child_process';

async function globalSetup( config: FullConfig ) {
	// Reset env before the tests run, so the settings in next set of tests can be applied using legacy UI.
	const SSH_LOGIN = process.env.SSH_LOGIN;
	const SSH_HOST = process.env.SSH_HOST;
	const SSH_PORT = process.env.SSH_PORT;
	const WP_VERSION = process.env.WP_VERSION ?? '6.9';
	const WP_TYPE = process.env.WP_TYPE ?? 'single';
	
	const remoteCmd = `$HOME/bin/reset-wp.sh --wp-version=${ WP_VERSION } --wp-type=${ WP_TYPE }`;
	const sshArgs = [
		`${ SSH_LOGIN }@${ SSH_HOST }`,
		'-p', SSH_PORT,
		'-o', 'StrictHostKeyChecking=no',
		remoteCmd,
	];

	console.log( `Executing: ssh ${ sshArgs.join( ' ' ) }` );

	execFileSync( 'ssh', sshArgs, {
		stdio: 'inherit',
		timeout: 60_000,
	} );

	const projectUse = config.projects[ 0 ].use;

	await restLogin( {
		baseURL: projectUse.baseURL,
		storageStatePath: String( projectUse.storageState ),
		httpCredentials: projectUse.httpCredentials,
		user: {
			// @ts-ignore
			username: process.env.WP_USERNAME,
			// @ts-ignore
			password: process.env.WP_PASSWORD,
		},
	} );

	await guestStorageState( {
		baseURL: projectUse.baseURL,
		httpCredentials: projectUse.httpCredentials,
		storageStatePath: `${ process.env.STORAGE_STATE_PATH }/guest.json`,
	} );
}

export default globalSetup;
