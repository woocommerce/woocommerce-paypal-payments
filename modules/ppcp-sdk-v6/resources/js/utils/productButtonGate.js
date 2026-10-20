/**
 * Gates the product-page express buttons behind the native add-to-cart state.
 *
 * When WooCommerce disables `.single_add_to_cart_button` (e.g. a variable
 * product with no variation chosen), this mirrors v5's SingleProductBootstrap
 * via the shared ButtonDisabler: it disables the wrapper so a click surfaces
 * WooCommerce's own validation instead of reaching ppc-change-cart.
 *
 * @package
 */

import { disable, enable, isDisabled } from '@ppcp-button/Helper/ButtonDisabler';
import { productForm } from '../endpointsAdapter';
import { hasJQuery } from './api';

// The native submit whose disabled state WooCommerce drives from the variation
// selection. Its absence marks a non-classic form (e.g. the block add-to-cart),
// which this gate deliberately leaves alone.
const ADD_TO_CART_SELECTOR = '.single_add_to_cart_button';

// Attached once per page, however many times init is called.
let initialized = false;
let buttonObserver = null;

/**
 * Resets module state. Test seam only.
 */
export function resetProductButtonGate() {
	initialized = false;
	if ( buttonObserver ) {
		buttonObserver.disconnect();
		buttonObserver = null;
	}
}

/**
 * Starts gating the product-page express buttons.
 *
 * No-ops off product pages and on forms without a classic add-to-cart button,
 * so it is safe to call unconditionally.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 */
export function initProductButtonGate( config ) {
	if ( 'product' !== config?.page_context || initialized ) {
		return;
	}

	const form = productForm();
	if ( ! form ) {
		return;
	}

	// A missing button is treated as "enabled", not as a reason to bail: a simple
	// product still has one and must stay live, and only a classic form carries
	// this selector, which scopes the gate to classic themes.
	const addToCartButton = form.querySelector( ADD_TO_CART_SELECTOR );

	const shouldEnable = () => {
		// Re-queried each time: WooCommerce can re-render the button.
		const button = form.querySelector( ADD_TO_CART_SELECTOR );
		return ! button || ! button.classList.contains( 'disabled' );
	};

	const sync = () => {
		const wrapper = document.querySelector( config.wrapper );
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

	if ( addToCartButton ) {
		buttonObserver = new MutationObserver( sync );
		buttonObserver.observe( addToCartButton, { attributes: true } );
	}

	// Covers quantity changes and the attribute selects.
	form.addEventListener( 'change', sync );

	if ( hasJQuery() ) {
		// WooCommerce fills variation_id and flips the add-to-cart button only
		// after these fire, so the change event above can run a beat too early.
		jQuery( form ).on( 'found_variation reset_data', sync );
	}
}
