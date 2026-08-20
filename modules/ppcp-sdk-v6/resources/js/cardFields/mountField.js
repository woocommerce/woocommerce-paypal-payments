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
 * Replaces a WC card input with its hosted v6 field, hiding the original.
 *
 * The element v6 returns is ours to size: `style.input` only reaches the text
 * inside it, and theme rules targeting the `input` tag never match it, so
 * without an explicit size it keeps the SDK default (much taller than the
 * form's inputs). Width fills the parent rather than copying the input's own
 * width, which is not the width its column gives it — WooCommerce renders the
 * CVV input far narrower than the half-width column holding it.
 *
 * @param {Object}      cardSession - The v6 card fields session.
 * @param {string}      fieldType   - number|expiry|cvv|name.
 * @param {HTMLElement} inputField  - The existing WC input to replace.
 */
export function mountField( cardSession, fieldType, inputField ) {
	if ( ! inputField || inputField.hidden ) {
		return;
	}

	const placeholder = inputField.getAttribute( 'placeholder' );
	const options = {
		type: fieldType,
		style: { input: hostedFieldTextStyles( inputField ) },
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
	fieldElement.style.height = window.getComputedStyle( inputField ).height;

	inputField.parentNode.appendChild( fieldElement );
	hide( inputField, true );
	inputField.hidden = true;
}
