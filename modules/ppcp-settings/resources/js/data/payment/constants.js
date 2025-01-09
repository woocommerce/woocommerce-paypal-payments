/**
 * Name of the module-store in the main Redux store.
 *
 * Helps to isolate data, used by reducer and selectors.
 *
 * @type {string}
 */
export const STORE_NAME = 'wc/paypal/payment';

/**
 * REST path to hydrate data of this module by loading data from the WP DB.
 *
 * Used by: Resolvers
 * See: PaymentRestEndpoint.php
 *
 * @type {string}
 */
export const REST_HYDRATE_PATH = '/wc/v3/wc_paypal/payment';
