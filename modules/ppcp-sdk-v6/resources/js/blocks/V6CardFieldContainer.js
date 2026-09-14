/**
 * Renders one v6 card field into React.
 *
 * React owns an empty container and never reconciles the SDK-owned subtree:
 * the field element and its label are appended imperatively and removed on
 * cleanup, and are recreated only when the session or field type changes.
 *
 * @package
 */

import { createElement, useEffect, useRef } from '@wordpress/element';

/**
 * @param {Object}  props                  - Component props.
 * @param {Object}  props.session          - The v6 card-fields session.
 * @param {string}  props.type             - The field type (number, expiry, cvv, name).
 * @param {Object}  props.style            - The `style.input` object for the field.
 * @param {string}  [props.height]         - CSS height for the field's own box.
 * @param {string}  [props.label]          - The field's visible name.
 * @param {boolean} [props.floatingLabel]  - Whether the page carries WooCommerce's floating-label CSS.
 * @param {boolean} [props.isActive]       - Whether the field is focused or filled.
 * @return {Object} The container element.
 */
export function V6CardFieldContainer( {
	session,
	type,
	style,
	height,
	label,
	floatingLabel = false,
	isActive = false,
} ) {
	const containerRef = useRef( null );

	useEffect( () => {
		const container = containerRef.current;
		if ( ! container || ! session ) {
			return undefined;
		}

		const options = { type, style: { input: style } };
		if ( label ) {
			// A floating label covers the empty field, leaving no room
			// for a placeholder; ariaLabel then carries the name.
			if ( floatingLabel ) {
				options.ariaLabel = label;
			} else {
				options.placeholder = label;
			}
		}

		const field = session.createCardFieldsComponent( options );
		// style.input only reaches the text inside the element. Its box keeps
		// the SDK default height, much taller than the form inputs, unless
		// sized here.
		field.style.width = '100%';
		if ( height ) {
			field.style.height = height;
		}
		container.appendChild( field );

		// WooCommerce's floating-label CSS expects the label after the input.
		// It is aria-hidden: the hosted field is a cross-origin iframe with
		// its own aria-label.
		let labelElement = null;
		if ( floatingLabel && label ) {
			labelElement = document.createElement( 'label' );
			labelElement.className = 'ppcp-sdk-v6-card-field__label';
			labelElement.textContent = label;
			labelElement.setAttribute( 'aria-hidden', 'true' );
			container.appendChild( labelElement );
		}

		return () => {
			field.remove();
			if ( labelElement ) {
				labelElement.remove();
			}
		};
		// Everything but session and type is captured at mount: depending on
		// it would recreate the field on every render.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ session, type ] );

	const classNames = [
		'ppcp-sdk-v6-card-field',
		`ppcp-sdk-v6-card-field--${ type }`,
	];
	if ( floatingLabel ) {
		classNames.push( 'wc-block-components-text-input' );
		if ( isActive ) {
			classNames.push( 'is-active' );
		}
	}

	return createElement( 'div', {
		ref: containerRef,
		className: classNames.join( ' ' ),
	} );
}
