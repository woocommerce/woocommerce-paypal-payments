/**
 * Name of the module-store in the main Redux store.
 *
 * Helps to isolate data, used by reducer and selectors.
 *
 * @type {string}
 */
export const STORE_NAME = 'wc/paypal/common';

/**
 * REST path to hydrate data of this module by loading data from the WP DB.
 *
 * Used by resolvers.
 *
 * @type {string}
 */
export const REST_HYDRATE_PATH = '/wc/v3/wc_paypal/common';

/**
 * REST path to fetch merchant details from the WordPress DB.
 *
 * Used by controls.
 *
 * @type {string}
 */
export const REST_HYDRATE_MERCHANT_PATH = '/wc/v3/wc_paypal/common/merchant';

/**
 * REST path to persist data of this module to the WP DB.
 *
 * Used by controls.
 *
 * @type {string}
 */
export const REST_PERSIST_PATH = '/wc/v3/wc_paypal/common';

/**
 * REST path to perform the manual connection authentication, using client ID and secret.
 *
 * Used by: Controls
 * See: AuthenticateRestEndpoint.php
 *
 * @type {string}
 */
export const REST_DIRECT_AUTHENTICATION_PATH =
	'/wc/v3/wc_paypal/authenticate/direct';

/**
 * REST path to perform the ISU authentication check, using shared ID and authCode.
 *
 * Used by: Controls
 * See: AuthenticateRestEndpoint.php
 *
 * @type {string}
 */
export const REST_ISU_AUTHENTICATION_PATH = '/wc/v3/wc_paypal/authenticate/isu';

/**
 * REST path to generate an ISU URL for the PayPal-login.
 *
 * Used by: Controls
 * See: LoginLinkRestEndpoint.php
 *
 * @type {string}
 */
export const REST_CONNECTION_URL_PATH = '/wc/v3/wc_paypal/login_link';

/**
 * REST path to fetch webhooks data or resubscribe webhooks,
 *
 * Used by: Controls
 * See: WebhookSettingsEndpoint.php
 *
 * @type {string}
 */
export const REST_WEBHOOKS = '/wc/v3/wc_paypal/webhook_settings';

/**
 * REST path to start webhook simulation and observe the state,
 *
 * Used by: Controls
 * See: WebhookSettingsEndpoint.php
 *
 * @type {string}
 */
export const REST_WEBHOOKS_SIMULATE = '/wc/v3/wc_paypal/webhook_simulate';

/**
 * REST path to refresh the feature status.
 *
 * Used by: Controls
 * See: RefreshFeatureStatusEndpoint.php
 *
 * @type {string}
 */
export const REST_REFRESH_FEATURES_PATH =
	'/wc/v3/wc_paypal/refresh-feature-status';
