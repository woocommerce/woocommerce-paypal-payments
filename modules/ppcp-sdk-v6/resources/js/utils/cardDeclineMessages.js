/**
 * Shopper-facing wording for a card session that did not succeed.
 *
 * v6 runs 3D Secure client-side, so a rejected card never reaches the PHP
 * capture gate that produces these messages in v5. The payment wording matches
 * CardFieldsModule::capture_rejection_message(); change it there first.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';

export const CARD_DECLINE_MESSAGE = __(
	'This card could not be authorized. Please try a different payment method.',
	'woocommerce-paypal-payments'
);

export const CARD_SAVE_DECLINE_MESSAGE = __(
	'This card could not be authorized. Please try a different card.',
	'woocommerce-paypal-payments'
);

/**
 * The message for a thrown error, keeping internal wording away from shoppers.
 *
 * Server errors are marked isUserFacing by the AJAX helper (utils/api.js) and
 * are already translated; anything else (an SDK error, a bug) falls back.
 *
 * @param {Object|Error} error    - The thrown error.
 * @param {string}       fallback - The message to show for an internal error.
 * @return {string} The message.
 */
export function userFacingMessage( error, fallback ) {
	return error?.isUserFacing && error.message ? error.message : fallback;
}

/**
 * Builds an error whose message the error handler renders verbatim.
 *
 * The classic-page handleError() only shows a message it knows is shopper-safe,
 * so a decline raised on the page must opt in the same way a server error does.
 *
 * @param {string} message - The shopper-facing message.
 * @return {Error} The error.
 */
export function userFacingError( message ) {
	const error = new Error( message );
	error.isUserFacing = true;
	return error;
}
