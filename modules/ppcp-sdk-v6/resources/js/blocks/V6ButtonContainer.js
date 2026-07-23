/**
 * Bridges a v6 Web Component button into React.
 *
 * React only ever renders an empty container div; the Web Component is
 * created, configured and appended imperatively, and removed on cleanup.
 * React must never reconcile the custom element's subtree, so nothing is
 * rendered as a child of the container.
 *
 * @package
 */

import { createElement, useEffect, useRef } from '@wordpress/element';
import { createMethodButton } from '../components/buttonRenderer';

/**
 * Mounts one funding-method button into a container div.
 *
 * The button is recreated only when the payment session changes (a new
 * session appears only when eligibility changes), so ordinary React
 * re-renders leave the mounted Web Component untouched.
 *
 * @param {Object}                            props                   - Component props.
 * @param {string}                            props.method            - The funding method (paypal, venmo, paylater).
 * @param {Object}                            props.session           - The payment session for the method.
 * @param {Object}                            props.styles            - Button styles for the current context.
 * @param {() => Promise<{orderId: string}>}  props.createOrderFn     - Returns the created order id.
 * @param {Object}                            [props.payLaterDetails] - Pay Later product details.
 * @param {() => void}                        [props.onClick]         - Called on click before the session starts.
 * @return {Object} The container element.
 */
export function V6ButtonContainer( {
	method,
	session,
	styles,
	createOrderFn,
	payLaterDetails,
	onClick,
} ) {
	const containerRef = useRef( null );
	// onClick identity changes every render (the Blocks registry passes a
	// fresh callback); read it through a ref so it does not recreate the
	// button, which must stay tied to the session identity only.
	const onClickRef = useRef( null );
	onClickRef.current = onClick;

	useEffect( () => {
		const container = containerRef.current;
		if ( ! container || ! session ) {
			return undefined;
		}

		const button = createMethodButton( {
			method,
			styles,
			session,
			createOrderFn,
			payLaterDetails,
			onClick: () => onClickRef.current?.(),
		} );
		if ( ! button ) {
			return undefined;
		}

		container.appendChild( button );

		return () => {
			button.remove();
		};
		// Recreate only when the method or its session changes. styles,
		// createOrderFn and payLaterDetails are captured at mount; they
		// only change alongside a new session (new eligibility), so
		// depending on them would recreate the button on every render.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ method, session ] );

	return createElement( 'div', { ref: containerRef } );
}
