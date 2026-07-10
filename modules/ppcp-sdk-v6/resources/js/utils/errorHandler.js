/**
 * Centralized error and cancel handling.
 *
 * @package
 */

/**
 * Handles a payment error by logging and showing a WC notice.
 *
 * @param {Object|Error} error - The error object.
 */
export function handleError( error ) {
	// eslint-disable-next-line no-console
	console.error( '[PPCP SDK v6]', error );

	const message =
		error?.message || 'An error occurred during the payment process.';

	if ( typeof jQuery !== 'undefined' ) {
		const $notices = jQuery(
			'.woocommerce-notices-wrapper, .woocommerce-error'
		).first();
		if ( $notices.length ) {
			const ul = document.createElement( 'ul' );
			ul.className = 'woocommerce-error';
			const li = document.createElement( 'li' );
			li.textContent = message;
			ul.appendChild( li );
			$notices.empty().append( ul );
			jQuery( 'html, body' ).animate(
				{ scrollTop: $notices.offset().top - 100 },
				500
			);
		}
	}
}

/**
 * Handles a payment cancellation.
 */
export function handleCancel() {
	// User closed the popup — no action needed.
}
