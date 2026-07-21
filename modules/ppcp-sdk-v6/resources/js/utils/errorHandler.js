/**
 * Centralized error handling.
 *
 * Delegates notice rendering to the shared v5 ErrorHandler so notices
 * look and behave the same across both button stacks (container
 * creation, role="alert", scroll-to-notice).
 *
 * @package
 */

import ErrorHandler from '@ppcp-button/ErrorHandler';
import { hasJQuery } from './api';

let errorLabels = {};

/**
 * Stores the translated labels from the localized config.
 *
 * @param {Object} labels - The config.labels object.
 */
export function setErrorLabels( labels ) {
	errorLabels = labels || {};
}

/**
 * Handles a payment error by logging and showing a WC notice.
 *
 * Server-provided messages (marked isUserFacing by the AJAX helper) are
 * shown verbatim; internal errors fall back to the translated generic
 * label so hardcoded English strings never reach shoppers.
 *
 * @param {Object|Error} error - The error object.
 */
export function handleError( error ) {
	// eslint-disable-next-line no-console
	console.error( '[PPCP SDK v6]', error );

	const wrapper =
		document.querySelector( '.woocommerce-notices-wrapper' ) ||
		document.querySelector( '.woocommerce' ) ||
		document.body;

	const handler = new ErrorHandler(
		errorLabels.generic_error || '',
		wrapper
	);

	// Expired-session validation failures need recalculated totals,
	// like in v5.
	if ( error?.refresh && hasJQuery() ) {
		jQuery( document.body ).trigger( 'update_checkout' );
	}

	if ( Array.isArray( error?.errors ) && error.errors.length ) {
		handler.clear();
		handler.messages( error.errors );
		return;
	}

	if ( error?.isUserFacing && error.message ) {
		handler.clear();
		handler.message( error.message );
		return;
	}

	if ( errorLabels.generic_error ) {
		handler.genericError();
	}
}
