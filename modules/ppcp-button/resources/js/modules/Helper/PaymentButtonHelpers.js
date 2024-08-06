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
 *
 * @param {string} defaultId     - Default wrapper ID.
 * @param {string} miniCartId    - Wrapper inside the mini-cart.
 * @param {string} smartButtonId - ID of the smart button wrapper.
 * @param {string} blockId       - Block wrapper ID (express checkout, block cart).
 * @param {string} gatewayId     - Gateway wrapper ID (classic checkout).
 * @return {{MiniCart, Gateway, Block, SmartButton, Default}} List of all wrapper IDs, by context.
 */
export function combineWrapperIds(
	defaultId = '',
	miniCartId = '',
	smartButtonId = '',
	blockId = '',
	gatewayId = ''
) {
	const sanitize = ( id ) => id.replace( /^#/, '' );

	return {
		Default: sanitize( defaultId ),
		SmartButton: sanitize( smartButtonId ),
		Block: sanitize( blockId ),
		Gateway: sanitize( gatewayId ),
		MiniCart: sanitize( miniCartId ),
	};
}

/**
 * Returns full payment button styles by combining the global ppcpConfig with
 * payment-method-specific styling provided via buttonConfig.
 *
 * @param {Object} ppcpConfig   - Global plugin configuration.
 * @param {Object} buttonConfig - Payment method specific configuration.
 * @return {{MiniCart: (*), Default: (*)}} Combined styles, separated by context.
 */
export function combineStyles( ppcpConfig, buttonConfig ) {
	return {
		Default: {
			...ppcpConfig.style,
			...buttonConfig.style,
		},
		MiniCart: {
			...ppcpConfig.mini_cart_style,
			...buttonConfig.mini_cart_style,
		},
	};
}

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

/**
 * Adds an event listener for the provided button event.
 *
 * @param {Object}   options                 - The options for the event listener.
 * @param {string}   options.event           - Event to observe.
 * @param {string}   [options.paymentMethod] - The payment method name (optional).
 * @param {Function} options.callback        - The callback function to execute when the event is triggered.
 * @throws {Error} Throws an error if the event is invalid.
 */
export function observeButtonEvent( { event, paymentMethod = '', callback } ) {
	if ( ! isValidButtonEvent( event ) ) {
		throw new Error( `Invalid event: ${ event }` );
	}

	const fullEventName = paymentMethod
		? `${ event }-${ paymentMethod }`
		: event;

	document.body.addEventListener( fullEventName, callback );
}
