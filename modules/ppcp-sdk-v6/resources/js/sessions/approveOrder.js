/**
 * Shared order approval handler for all payment sessions.
 *
 * @package
 */

import { handleError } from '../utils/errorHandler';

/**
 * Approves (captures) an order via the WC AJAX endpoint.
 *
 * @param {Object} ajaxConfig          - The ajax.approve_order config.
 * @param {string} ajaxConfig.endpoint - The endpoint URL.
 * @param {string} ajaxConfig.nonce    - The nonce.
 * @param {string} orderId             - The PayPal order ID.
 */
export async function approveOrder( ajaxConfig, orderId ) {
	const response = await fetch( ajaxConfig.endpoint, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify( {
			nonce: ajaxConfig.nonce,
			order_id: orderId,
		} ),
	} );

	const result = await response.json();

	if ( result.data?.redirect_url ) {
		window.location.href = result.data.redirect_url;
	} else if ( result.success ) {
		window.location.reload();
	} else {
		handleError( {
			message: result.data?.message || 'Order approval failed.',
		} );
	}
}
