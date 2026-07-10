/**
 * Detects the current WooCommerce page context.
 *
 * @package
 */

/**
 * Detects the page context from DOM selectors.
 *
 * @return {string} The context: 'product', 'cart', 'checkout', 'mini-cart', or empty string.
 */
export function detectContext() {
	if ( document.querySelector( '.single-product' ) ) {
		return 'product';
	}
	if ( document.querySelector( '.woocommerce-cart' ) ) {
		return 'cart';
	}
	if ( document.querySelector( '.woocommerce-checkout' ) ) {
		return 'checkout';
	}
	if ( document.querySelector( '.widget_shopping_cart' ) ) {
		return 'mini-cart';
	}

	return '';
}
