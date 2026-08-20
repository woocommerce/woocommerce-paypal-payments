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
import { cardFieldStyles } from './cardFieldStyles';

/**
 * Replaces a WC card input with its hosted v6 field, hiding the original
 * (mirrors v5's Render.js placement/hiding).
 *
 * Unlike v5's paypal.CardFields(), which owns its container's layout via its
 * own .render() call, v6's createCardFieldsComponent() only returns a plain
 * HTMLElement: `style.input` styles what's inside it (font, color, padding,
 * ...), not the element's own box on this page. Theme rules targeting the
 * original `input` tag (e.g. `.input-wrapper input { height: 100% }`) don't
 * match this element, so its box has to be sized explicitly from the original
 * input's own rendered dimensions or it falls back to the SDK's default (much
 * taller than the form's inputs).
 *
 * @param {Object}      cardSession - The v6 card fields session.
 * @param {string}      fieldType   - number|expiry|cvv|name.
 * @param {HTMLElement} inputField  - The existing WC input to replace.
 */
export function mountField( cardSession, fieldType, inputField ) {
	if ( ! inputField || inputField.hidden ) {
		return;
	}

	const computed = window.getComputedStyle( inputField );
	const options = {
		type: fieldType,
		style: { input: cardFieldStyles( inputField ) },
	};
	if ( inputField.getAttribute( 'placeholder' ) ) {
		options.placeholder = inputField.getAttribute( 'placeholder' );
	}

	const fieldElement = cardSession.createCardFieldsComponent( options );
	fieldElement.style.width = computed.width;
	fieldElement.style.height = computed.height;

	inputField.parentNode.appendChild( fieldElement );
	hide( inputField, true );
	inputField.hidden = true;
}
