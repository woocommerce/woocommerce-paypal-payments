/**
 * Helper function used by PaymentButton instances.
 *
 * @file
 */

/**
 * Collection of recognized event names for payment button events.
 *
 * @type {Object}
 */
export const ButtonEvents = Object.freeze( {
	INVALIDATE: 'ppcp_invalidate_methods',
	RENDER: 'ppcp_render_method',
	REDRAW: 'ppcp_redraw_method',
} );

/**
 * Verifies if the given event name is a valid Payment Button event.
 *
 * @param {string} event - The event name to verify.
 * @return {boolean} True, if the event name is valid.
 */
export function isValidButtonEvent( event ) {
	const buttonEventValues = Object.values( ButtonEvents );

	return buttonEventValues.includes( event );
}

/**
 * Dispatches a payment button event.
 *
 * @param {Object} options                 - The options for dispatching the event.
 * @param {string} options.event           - Event to dispatch.
 * @param {string} [options.paymentMethod] - Optional. Name of payment method, to target a specific button only.
 * @throws {Error} Throws an error if the event is invalid.
 */
export function dispatchButtonEvent( { event, paymentMethod = '' } ) {
	if ( ! isValidButtonEvent( event ) ) {
		throw new Error( `Invalid event: ${ event }` );
	}

	const fullEventName = paymentMethod
		? `${ event }-${ paymentMethod }`
		: event;

	document.body.dispatchEvent( new Event( fullEventName ) );
}
