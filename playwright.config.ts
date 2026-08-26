/**
 * External dependencies
 */
import { defineConfig, devices, ViewportSize } from '@playwright/test';
import { WpCliEnvType } from '@inpsyde/playwright-utils/build/@types/wp-cli';
import dotenv from 'dotenv';
import path from 'path';
/**
 * Internal dependencies
 */
import { BaseExtend } from './tests/qa/utils';

const dotenvPath = process.env.CI
	? path.resolve( __dirname, '.env.ci' )
	: undefined;
dotenv.config( { path: dotenvPath } );

const viewportSize: ViewportSize = { width: 1280, height: 850 };

export default defineConfig< BaseExtend >( {
	testDir: 'tests/qa/tests',
	expect: {
		timeout: 20_000,
	},
	timeout: 2 * 60_000,
	/* Run tests in files in parallel */
	fullyParallel: false,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !! process.env.CI,
	/* Retry on CI only */
	retries: process.env.CI ? 1 : 0,
	/* Opt out of parallel tests on CI. */
	workers: process.env.CI ? 1 : 1,
	/* The base directory, relative to the config file, for snapshot files created with toMatchSnapshot */
	snapshotDir: './snapshots',
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: [
		[ 'list' ],
		[ 'html', { outputFolder: 'playwright-report' } ],
		[
			'@inpsyde/playwright-utils/build/integration/jira/xray-reporter.js',
			{
				apiClient: {
					client_id: process.env.XRAY_CLIENT_ID,
					client_secret: process.env.XRAY_CLIENT_SECRET,
				},
				testExecutionKey: process.env.XRAY_TEST_EXEC_KEY,
			},
		],
	],
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */

	globalSetup: require.resolve( './tests/qa/global-setup' ),

	use: {
		baseURL: process.env.WP_BASE_URL,

		storageState: process.env.STORAGE_STATE_PATH_ADMIN,

		ignoreHTTPSErrors: process.env.IGNORE_HTTPS_ERRORS === 'true',

		/**
		 * For envs with Basic Auth. Omit when both are empty (e.g. wp-env).
		 */
		...( process.env.WP_BASIC_AUTH_USER &&
			process.env.WP_BASIC_AUTH_PASS && {
				httpCredentials: {
					username: process.env.WP_BASIC_AUTH_USER,
					password: process.env.WP_BASIC_AUTH_PASS,
				},
			} ),

		...devices[ 'Desktop Chrome' ],

		launchOptions: {
			// Put your chromium-specific args here
			args: [ '--disable-web-security' ],
		},

		viewport: viewportSize,

		trace: process.env.CI
			? 'off'
			: { mode: 'retain-on-failure', screenshots: false, snapshots: true, sources: true },

		screenshot: {
			mode: 'only-on-failure',
			fullPage: true, // Captures entire scrollable page
		},

		recordVideoOptions: process.env.CI
			? undefined
			: {
				size: viewportSize,
			},

		cliConfig: {
			envType: process.env.WPCLI_ENV_TYPE as WpCliEnvType,
			path: process.env.WPCLI_PATH,
			ssh: {
				login: process.env.SSH_LOGIN,
				host: process.env.SSH_HOST,
				port: process.env.SSH_PORT,
				path: process.env.SSH_PATH,
			},
		},
	},

	projects: [
		// Manual setup (grep scripts target this — no dependency chain)
		{
			name: 'setup',
			testMatch: /(01-env|02-woocommerce|03-pcp)\.setup\.ts/,
			fullyParallel: false,
		},
		
		// WooCommerce (dependency target only)
		{
			name: 'setup-woocommerce',
			testMatch: /02-woocommerce\.setup\.ts/,
			fullyParallel: false,
		},

		// PCP setups by country (dependency targets)
		{
			name: 'setup-pcp-usa',
			dependencies: [ 'setup-woocommerce' ],
			testMatch: /03-pcp\.setup\.ts/,
			grep: /setup:pcp:usa;/,
			fullyParallel: false,
		},
		{
			name: 'setup-pcp-germany',
			dependencies: [ 'setup-woocommerce' ],
			testMatch: /03-pcp\.setup\.ts/,
			grep: /setup:pcp:germany;/,
			fullyParallel: false,
		},
		{
			name: 'setup-pcp-mexico',
			dependencies: [ 'setup-woocommerce' ],
			testMatch: /03-pcp\.setup\.ts/,
			grep: /setup:pcp:mexico;/,
			fullyParallel: false,
		},

		// Setup per test set (dependency targets)
		{
			name: 'setup-transaction-usa',
			dependencies: [ 'setup-pcp-usa' ],
			testMatch: /03-pcp\.setup\.ts/,
			grep: /setup:transaction:usa;/,
			fullyParallel: false,
		},
		{
			name: 'setup-transaction-mexico',
			dependencies: [ 'setup-pcp-mexico' ],
			testMatch: /03-pcp\.setup\.ts/,
			grep: /setup:transaction:mexico;/,
			fullyParallel: false,
		},
		{
			name: 'setup-transaction-germany',
			dependencies: [ 'setup-pcp-germany' ],
			testMatch: /03-pcp\.setup\.ts/,
			grep: /setup:transaction:germany;/,
			fullyParallel: false,
		},
		{
			name: 'setup-vaulting',
			dependencies: [ 'setup-pcp-usa' ],
			testMatch: /03-pcp\.setup\.ts/,
			grep: /setup:vaulting;/,
			fullyParallel: false,
		},
		{
			name: 'setup-subscription',
			dependencies: [ 'setup-pcp-usa' ],
			testMatch: /03-pcp\.setup\.ts/,
			grep: /setup:subscription;/,
			fullyParallel: false,
		},

		// Test projects
		{
			name: 'stress',
			dependencies: [ 'setup-woocommerce' ],
			testMatch: /stress\.spec\.ts/,
		},

		// =============================================================
		// Project shards (for parallel/separate executions)
		// =============================================================
		{
			name: 'shard:plugin-foundation',
			dependencies: [ 'setup-woocommerce' ],
			testMatch: /01-plugin-foundation\/.*\.spec\.ts/,
		},
		{
			name: 'shard:onboarding',
			dependencies: [ 'setup-woocommerce' ],
			testMatch: /02-onboarding\/.*\.spec\.ts/,
		},
		{
			name: 'shard:settings',
			dependencies: [ 'setup-woocommerce' ],
			testMatch: /03-plugin-settings\/.*\.spec\.ts/,
		},
		{
			name: 'shard:transaction-usa',
			dependencies: [ 'setup-transaction-usa' ],
			testMatch: /05-transactions\/transaction-usa.*\.spec\.ts/,
		},
		{
			name: 'shard:transaction-mexico',
			dependencies: [ 'setup-transaction-mexico' ],
			testMatch: /05-transactions\/transaction-mexico.*\.spec\.ts/,
		},
		{
			name: 'shard:transaction-germany',
			dependencies: [ 'setup-transaction-germany' ],
			testMatch: /05-transactions\/transaction-germany.*\.spec\.ts/,
		},
		{
			name: 'shard:refund',
			dependencies: [ 'setup-transaction-usa' ],
			testMatch: /06-refund\/.*\.spec\.ts/,
		},
		{
			name: 'shard:vaulting',
			dependencies: [ 'setup-vaulting' ],
			testMatch: /07-vaulting\/.*\.spec\.ts/,
		},
		{
			name: 'shard:subscription',
			dependencies: [ 'setup-subscription' ],
			testMatch: /08-subscription\/.*\.spec\.ts/,
		},
	],
} );
