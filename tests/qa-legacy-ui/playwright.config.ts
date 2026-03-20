/**
 * External dependencies
 */
import { WpCliEnvType } from '@inpsyde/playwright-utils/build/@types/wp-cli';
import { defineConfig, devices } from '@playwright/test';
/**
 * Internal dependencies
 */
import { BaseExtend } from './utils/test';
import { frontendUiProjects } from 'tests/05-frontend-ui/_test-setup/_projects';
import { transactionProjects } from 'tests/06-transaction/_test-setup/_projects';
require( 'dotenv' ).config();

export default defineConfig< BaseExtend >( {
	testDir: 'tests',
	expect: {
		timeout: 20_000,
	},
	timeout: 2 * 60_000,
	/* Run tests in files in parallel */
	fullyParallel: false,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !! process.env.CI,
	/* Retry on CI only */
	retries: process.env.CI ? 2 : 0,
	/* Opt out of parallel tests on CI. */
	workers: process.env.CI ? 1 : 1,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: process.env.CI
		? [
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

		httpCredentials: {
			username: process.env.WP_BASIC_AUTH_USER,
			password: process.env.WP_BASIC_AUTH_PASS,
		},

		...devices[ 'Desktop Chrome' ],

		launchOptions: {
			// Put your chromium-specific args here
			args: [ '--disable-web-security' ],
		},

		viewport: { width: 1280, height: 850 },

    	trace: process.env.CI ? 'off' : 'retain-on-failure',//'on-first-retry',//'on',//

		screenshot: {
			mode: 'only-on-failure',
			fullPage: true, // Captures entire scrollable page
		},

		video: process.env.CI ? 'off' : {
			mode: 'retain-on-failure', //'on',//
			size: { width: 1280, height: 850 },
		},

		recordVideoOptions: process.env.CI ? undefined : {
			mode: 'retain-on-failure',
			size: { width: 1280, height: 850 },
		},

		cliConfig: {
			envType: process.env.WPCLI_ENV_TYPE as WpCliEnvType,
			path: String( process.env.WPCLI_PATH ),
			ssh: {
				login: process.env.SSH_LOGIN,
				host: process.env.SSH_HOST,
				port: process.env.SSH_PORT,
				path: process.env.SSH_PATH,
			}
		},
	},

	projects: [
		{
			name: 'setup-woocommerce',
			testMatch: /woocommerce\.setup\.ts/,
			fullyParallel: false,
		},
		{
			name: 'setup-pcp',
			testMatch: /pcp\.setup\.ts/,
			fullyParallel: false,
		},
		...frontendUiProjects,
		...transactionProjects,
	],
} );
