/**
 * Renders one v6 card field into React.
 *
 * React owns an empty container and never reconciles the SDK-owned subtree:
 * the field element is appended imperatively and removed on cleanup, and is
 * recreated only when the session or field type changes.
 *
 * @package
 */

import { createElement, useEffect, useRef } from '@wordpress/element';

/**
 * @param {Object} props                  - Component props.
 * @param {Object} props.session          - The v6 card-fields session.
 * @param {string} props.type             - The field type (number, expiry, cvv, name).
 * @param {Object} props.style            - The `style.input` object for the field.
 * @param {string} [props.height]         - CSS height for the field's own box.
 * @param {string} [props.placeholder]    - The field placeholder text.
 * @param {Object} [props.containerStyle] - Extra styles for the wrapper (e.g. flex sizing).
 * @return {Object} The container element.
 */
export function V6CardFieldContainer( {
	session,
	type,
	style,
	height,
	placeholder,
	containerStyle,
} ) {
	const containerRef = useRef( null );

	useEffect( () => {
		const container = containerRef.current;
		if ( ! container || ! session ) {
			return undefined;
		}

		const options = { type, style: { input: style } };
		if ( placeholder ) {
			options.placeholder = placeholder;
		}

		const field = session.createCardFieldsComponent( options );
		// style.input only reaches the text inside the element, so its box is
		// sized here or it keeps the SDK default (much taller than the form
		// inputs) — hence height as its own prop rather than part of style.
		field.style.width = '100%';
		if ( height ) {
			field.style.height = height;
		}
		container.appendChild( field );

		return () => {
			field.remove();
		};
		// style, height and placeholder are captured at mount: depending on them
		// would recreate the field on every render.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ session, type ] );

	return createElement( 'div', {
		ref: containerRef,
		className: `ppcp-sdk-v6-card-field ppcp-sdk-v6-card-field--${ type }`,
		style: containerStyle,
	} );
}
