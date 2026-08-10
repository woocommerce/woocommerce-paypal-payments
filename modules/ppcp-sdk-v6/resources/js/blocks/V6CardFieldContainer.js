/**
 * Bridges one v6 card-fields Web Component into React.
 *
 * Like V6ButtonContainer, React renders an empty container and never
 * reconciles the SDK-owned subtree: the field element is appended
 * imperatively and removed on cleanup. The element is recreated only when the
 * session (or field type) changes, so ordinary re-renders leave it untouched.
 *
 * @package
 */

import { createElement, useEffect, useRef } from '@wordpress/element';

/**
 * @param {Object} props               - Component props.
 * @param {Object} props.session       - The v6 card-fields session.
 * @param {string} props.type          - The field type (number, expiry, cvv, name).
 * @param {Object} props.style         - The `style.input` object for the field.
 * @param {string} [props.placeholder] - The field placeholder text.
 * @return {Object} The container element.
 */
export function V6CardFieldContainer( { session, type, style, placeholder } ) {
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
		// v6 returns a bare element; let it fill the container, whose width the
		// block form controls, so the field lines up with the other inputs.
		field.style.width = '100%';
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
	} );
}
