/**
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';
require( 'dotenv' ).config();

/**
 * Internal dependencies
 */
import { BaseExtend } from '@inpsyde/playwright-utils/build';
const customSnapshotPathTemplate = '{snapshotDir}/{testFileDir}/{testFileName}-snapshots/{arg}{ext}';

export default defineConfig< BaseExtend >( {
	testDir: 'tests',
	snapshotPathTemplate: customSnapshotPathTemplate,
	timeout: 2 * 60 * 1000,
	/* Run tests in files in parallel */
	fullyParallel: false,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !! process.env.CI,
	retries: 0,
	workers: 1,
	/* The base directory, relative to the config file, for snapshot files created with toMatchSnapshot */
	snapshotDir: './snapshots',
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: process.env.CI
		? [
				[ 'list' ],
			// [ 'html', { outputFolder: 'playwright-report' } ],
			[
				'@inpsyde/playwright-utils/build/integration/jira/xray-reporter.js',
				{
					apiClient: {
						client_id: process.env.XRAY_CLIENT_ID,
						client_secret: process.env.XRAY_CLIENT_SECRET,
					},
					testExecutionKey: process.env.TEST_EXEC_KEY,
				},
			],
		]
		: [
				[ 'list' ],
				[ 'html', { outputFolder: 'playwright-report' } ],
			[
				'@inpsyde/playwright-utils/build/integration/jira/xray-reporter.js',
				{
					apiClient: {
						client_id: process.env.XRAY_CLIENT_ID,
						client_secret: process.env.XRAY_CLIENT_SECRET,
					},
					testExecutionKey: process.env.TEST_EXEC_KEY,
				},
			],
		],
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */

	globalSetup: require.resolve( './global-setup' ),

	use: {
		baseURL: process.env.WP_BASE_URL,

		storageState: process.env.STORAGE_STATE_PATH_ADMIN,

		ignoreHTTPSErrors: process.env.IGNORE_HTTPS_ERRORS === 'true',

		/**
		 * For envs with Basic Auth
		 */
		httpCredentials: {
			username: process.env.WP_BASIC_AUTH_USER,
			password: process.env.WP_BASIC_AUTH_PASS,
		},

		/* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
		trace: 'on-first-retry',

		// Capture screenshot after each test failure.
		screenshot: 'only-on-failure', //'off', //

		// Record video only when retrying a test for the first time.
		video: 'retain-on-failure', //'on', //

		...devices[ 'Desktop Chrome' ],

		launchOptions: {
			// Put your chromium-specific args here
			args: [
				'--font-render-hinting=none',
				'--disable-web-security',
				'--disable-features=TranslateUI',
				'--disable-ipc-flooding-protection',
			]
		},

		viewport: { width: 1024, height: 768 },

		// Used for Kinsta, to clear cache
		// sshConfig: {
		// 	login: process.env.SSH_LOGIN,
		// 	host: process.env.SSH_HOST,
		// 	port: process.env.SSH_PORT,
		// 	path: process.env.SSH_PATH,
		// },
	},
	outputDir: './test-results/report',
	projects: [
		{
			name: 'setup-woocommerce',
			testMatch: /woocommerce\.setup\.ts/,
			fullyParallel: false,
		},
		{
			name: 'setup-store',
			testMatch: /store\.setup\.ts/,
			fullyParallel: false,
		},
		{
			name: 'all',
			dependencies: [ 'setup-woocommerce' ],
		},
	],
} );
