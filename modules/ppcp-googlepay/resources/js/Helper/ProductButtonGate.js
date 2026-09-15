/**
 * Gates the product-page Google Pay button behind the native add-to-cart state.
 *
 * The v5 product bootstrap disables only its own wrapper, and this button's
 * container is a sibling of it rather than a descendant (SmartButton::button_renderer()
 * prints both inside .ppc-button-wrapper), so `.ppcp-disabled` never reaches it.
 * Without this the button would sit enabled next to a disabled "Add to cart",
 * offering a payment for a variable product with no variation chosen.
 *
 * @file
 */

import {
	disable,
	enable,
	isDisabled,
} from '@ppcp-button/Helper/ButtonDisabler';

const FORM_SELECTOR = 'form.cart';

// The native submit whose disabled state WooCommerce drives from the variation
// selection. Its absence means nothing gates the purchase, so the button stays live.
const ADD_TO_CART_SELECTOR = '.single_add_to_cart_button';

// Attached once per page, however many times init is called.
let initialized = false;

/**
 * Resets module state. Test seam only.
 */
export function resetProductButtonGate() {
	initialized = false;
}

/**
 * Starts gating the product-page Google Pay button.
 *
 * No-ops off the product page and on pages without a classic add-to-cart form,
 * so it is safe to call unconditionally.
 *
 * @param {string} wrapperSelector - Selector of the button's container.
 * @param {string} context         - The current page context.
 */
export function initProductButtonGate( wrapperSelector, context ) {
	if ( 'product' !== context || initialized || ! wrapperSelector ) {
		return;
	}

	const form = document.querySelector( FORM_SELECTOR );
	if ( ! form ) {
		return;
	}

	const shouldEnable = () => {
		// Re-queried each time: WooCommerce re-renders the button as the
		// variation selection changes.
		const button = form.querySelector( ADD_TO_CART_SELECTOR );

		return ! button || ! button.classList.contains( 'disabled' );
	};

	const sync = () => {
		const wrapper = document.querySelector( wrapperSelector );
		if ( ! wrapper ) {
			return;
		}

		const enableNow = shouldEnable();
		const wasDisabled = isDisabled( wrapper );

		// Only act on a transition: disable() binds a fresh mouseup handler each
		// call, so calling it twice without an intervening enable() would stack
		// handlers and submit the form more than once.
		if ( enableNow && wasDisabled ) {
			enable( wrapper );
		} else if ( ! enableNow && ! wasDisabled ) {
			disable( wrapper, form );
		}
	};

	initialized = true;

	sync();

	const addToCartButton = form.querySelector( ADD_TO_CART_SELECTOR );
	if ( addToCartButton ) {
		new MutationObserver( sync ).observe( form, {
			subtree: true,
			attributes: true,
			attributeFilter: [ 'class' ],
		} );
	}

	// Covers quantity changes and the attribute selects.
	form.addEventListener( 'change', sync );

	if ( window.jQuery ) {
		// WooCommerce fills variation_id and flips the add-to-cart button only
		// after these fire, so the change event above can run a beat too early.
		window.jQuery( form ).on( 'found_variation reset_data', sync );
	}
}
