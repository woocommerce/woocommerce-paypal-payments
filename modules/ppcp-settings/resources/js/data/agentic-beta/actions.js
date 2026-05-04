/**
 * Agentic Beta Actions: Side-effect functions for the agentic beta banner.
 *
 * These functions trigger server-side operations via the REST API.
 *
 * @file
 */

import apiFetch from '@wordpress/api-fetch';

const REST_DISMISS_PATH = '/wc/v3/wc_paypal/agentic-beta-banner-dismiss';

export const dismissAgenticBetaBanner = () =>
	apiFetch( {
		path: REST_DISMISS_PATH,
		method: 'POST',
	} );
