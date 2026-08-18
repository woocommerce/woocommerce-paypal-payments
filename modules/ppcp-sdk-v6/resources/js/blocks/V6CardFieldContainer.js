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
 * @param {string} [props.placeholder]    - The field placeholder text.
 * @param {Object} [props.containerStyle] - Extra styles for the wrapper (e.g. flex sizing).
 * @return {Object} The container element.
 */
export function V6CardFieldContainer( {
	session,
	type,
	style,
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
		// style.input only styles the inside of the element, so size its box here
		// or it falls back to the SDK default (much taller than the form inputs).
		field.style.width = '100%';
		if ( style?.height ) {
			field.style.height = style.height;
		}
		container.appendChild( field );

		return () => {
			field.remove();
		};
		// style and placeholder are captured at mount: depending on them would
		// recreate the field on every render.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ session, type ] );

	return createElement( 'div', {
		ref: containerRef,
		className: `ppcp-sdk-v6-card-field ppcp-sdk-v6-card-field--${ type }`,
		style: containerStyle,
	} );
}
