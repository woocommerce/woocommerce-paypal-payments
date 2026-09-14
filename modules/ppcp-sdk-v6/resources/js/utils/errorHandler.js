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

/**
 * Handles a recoverable payment problem: a card decline or a validation failure
 * the buyer can correct.
 *
 * Deliberately does less than handleError(): the buyer is still in the SDK's
 * card form and can retry, so nothing here refreshes the cart, navigates, or
 * tears the button down. Existing notices survive too — clearing them would
 * wipe WooCommerce's own validation messages.
 *
 * @param {Object} warning - The warning object, with code, name and message.
 */
export function handleWarning( warning ) {
	// eslint-disable-next-line no-console
	console.warn( '[PPCP SDK v6]', warning );

	if ( ! errorLabels.card_declined ) {
		return;
	}

	const wrapper =
		document.querySelector( '.woocommerce-notices-wrapper' ) ||
		document.querySelector( '.woocommerce' ) ||
		document.body;

	new ErrorHandler( errorLabels.generic_error || '', wrapper ).message(
		errorLabels.card_declined
	);
}
