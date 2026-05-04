/**
 * Agentic Beta Actions: Side-effect functions for the agentic beta banner.
 *
 * These functions trigger server-side operations via the REST API.
 *
 * @file
 */

import apiFetch from '@wordpress/api-fetch';

const REST_BASE_PATH = '/wc/v3/wc_paypal/agentic-beta-banner';

export const dismissAgenticBetaBanner = () =>
	apiFetch( {
		path: `${ REST_BASE_PATH }/dismiss`,
		method: 'POST',
	} );

export const applyForAgenticBeta = () =>
	apiFetch( {
		path: `${ REST_BASE_PATH }/apply`,
		method: 'POST',
	} );
