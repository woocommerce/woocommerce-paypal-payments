import { debounce } from '../../../../../ppcp-blocks/resources/js/Helper/debounce';

const REFRESH_BUTTON_EVENT = 'ppcp_refresh_payment_buttons';

/**
 * Triggers a refresh of the payment buttons.
 * This function dispatches a custom event that the button components listen for.
 *
 * Use this function on the front-end to update payment buttons after the checkout form was updated.
 */
export function refreshButtons() {
	document.dispatchEvent( new Event( REFRESH_BUTTON_EVENT ) );
}

/**
 * Sets up event listeners for various cart and checkout update events.
 * When these events occur, it triggers a refresh of the payment buttons.
 *
 * @param {Function} refresh - Callback responsible to re-render the payment button.
 */
export function setupButtonEvents( refresh ) {
	const miniCartInitDelay = 1000;
	const debouncedRefresh = debounce( refresh, 50 );

	// Listen for our custom refresh event.
	document.addEventListener( REFRESH_BUTTON_EVENT, debouncedRefresh );

	// Listen for cart and checkout update events.
	// Note: we need jQuery here, because WooCommerce uses jQuery.trigger() to dispatch the events.
	window
		.jQuery( 'body' )
		.on( 'updated_cart_totals', debouncedRefresh )
		.on( 'updated_checkout', debouncedRefresh );

	// Use setTimeout for fragment events to avoid unnecessary refresh on initial render.
	setTimeout( () => {
		document.body.addEventListener(
			'wc_fragments_loaded',
			debouncedRefresh
		);
		document.body.addEventListener(
			'wc_fragments_refreshed',
			debouncedRefresh
		);
	}, miniCartInitDelay );
}
