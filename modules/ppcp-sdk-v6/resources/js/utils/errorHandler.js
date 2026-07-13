/**
 * Centralized error handling.
 *
 * @package
 */

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
 * @param {Object|Error} error - The error object.
 */
export function handleError( error ) {
	// eslint-disable-next-line no-console
	console.error( '[PPCP SDK v6]', error );

	const message = error?.message || errorLabels.generic_error || '';
	if ( ! message ) {
		return;
	}

	const wrapper = document.querySelector( '.woocommerce-notices-wrapper' );
	if ( ! wrapper ) {
		return;
	}

	const ul = document.createElement( 'ul' );
	ul.className = 'woocommerce-error';
	ul.setAttribute( 'role', 'alert' );
	const li = document.createElement( 'li' );
	li.textContent = message;
	ul.appendChild( li );

	// Append rather than replace, to preserve existing WC notices.
	wrapper.appendChild( ul );
	ul.scrollIntoView( { behavior: 'smooth', block: 'center' } );
}
