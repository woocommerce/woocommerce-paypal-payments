/**
 * Mounts one v6 card field into an existing WC input's slot.
 *
 * Shared by the classic checkout renderer and the add-payment-method save
 * renderer, which mount the same fields into the same WC card-form inputs and
 * previously carried identical copies of this function.
 *
 * @package
 */

import { hide } from '@ppcp-button/Helper/Hiding';
import { hostedFieldTextStyles } from './cardFieldStyles';

/**
 * Reads the input's rendered height, briefly undoing the hiding applied here.
 *
 * getComputedStyle() resolves height to pixels only for an element that has a
 * layout box, and yields the literal "auto" otherwise. Showing and restoring
 * happen within one task, so nothing is painted in between. display is set
 * directly rather than through show()/hide(), which broadcast jQuery events
 * other code listens for.
 *
 * @param {HTMLElement} inputField - The WC input being replaced.
 * @return {string|null} The height in pixels, or null when it has no box.
 */
function measureHeight( inputField ) {
	const wasHidden = inputField.hidden;
	const display = inputField.style.getPropertyValue( 'display' );
	const priority = inputField.style.getPropertyPriority( 'display' );

	inputField.hidden = false;
	inputField.style.removeProperty( 'display' );

	const height = window.getComputedStyle( inputField ).height;

	if ( display ) {
		inputField.style.setProperty( 'display', display, priority );
	}
	inputField.hidden = wasHidden;

	return height.endsWith( 'px' ) ? height : null;
}

/**
 * Sizes the field to the input it replaced, correcting later when the form is
 * not on screen yet.
 *
 * WooCommerce keeps the new-card form display:none while a saved payment token
 * is selected, so a field mounted there has no layout box and measures as auto.
 * The observer applies the height once the form is revealed. Nothing in the
 * payment flow depends on the height being correct.
 *
 * @param {HTMLElement} fieldElement - The mounted v6 field.
 * @param {HTMLElement} inputField   - The WC input being replaced.
 */
function applyHeight( fieldElement, inputField ) {
	const height = measureHeight( inputField );
	if ( height ) {
		fieldElement.style.height = height;
		return;
	}

	if ( typeof ResizeObserver !== 'function' ) {
		return;
	}

	const observer = new ResizeObserver( () => {
		const corrected = measureHeight( inputField );
		if ( ! corrected ) {
			return;
		}

		// Disconnect first: the write below resizes the observed element.
		observer.disconnect();
		fieldElement.style.height = corrected;
	} );
	observer.observe( fieldElement );
}

/**
 * Replaces a WC card input with its hosted v6 field, hiding the original.
 *
 * The element v6 returns is ours to size: `style.input` only reaches the text
 * inside it, and theme rules targeting the `input` tag never match it, so
 * without an explicit size it keeps the SDK default (much taller than the
 * form's inputs). Width fills the parent rather than copying the input's own
 * width, which is not the width its column gives it — WooCommerce renders the
 * CVV input far narrower than the half-width column holding it.
 *
 * @param {Object}      cardSession      - The v6 card fields session.
 * @param {string}      fieldType        - number|expiry|cvv|name.
 * @param {HTMLElement} inputField       - The existing WC input to replace.
 * @param {Object}      [styleOverrides] - Merchant overrides for the field text.
 */
export function mountField(
	cardSession,
	fieldType,
	inputField,
	styleOverrides
) {
	if ( ! inputField || inputField.hidden ) {
		return;
	}

	const placeholder = inputField.getAttribute( 'placeholder' );
	const options = {
		type: fieldType,
		style: { input: hostedFieldTextStyles( inputField, styleOverrides ) },
	};
	if ( placeholder ) {
		options.placeholder = placeholder;
	}

	const fieldElement = cardSession.createCardFieldsComponent( options );
	fieldElement.classList.add(
		'ppcp-sdk-v6-card-field',
		`ppcp-sdk-v6-card-field--${ fieldType }`
	);
	fieldElement.style.width = '100%';

	inputField.parentNode.appendChild( fieldElement );
	applyHeight( fieldElement, inputField );

	hide( inputField, true );
	inputField.hidden = true;
}
