/**
 * Adapter for the existing (v5) WC AJAX order endpoints.
 *
 * All v6 knowledge of the v5 endpoint contract lives here:
 * ppc-change-cart, ppc-create-order, ppc-approve-order, ppc-update-shipping.
 * Keep the request/response shapes in sync with the endpoint contract tests.
 *
 * @package
 */

import SingleProductActionHandler from '@ppcp-button/ActionHandler/SingleProductActionHandler';
import { postJson } from './utils/api';

/**
 * Collects products from the single product form for ppc-change-cart.
 *
 * Reuses the v5 product collection, which handles simple, variable,
 * grouped and booking products plus extra third-party form fields.
 *
 * @return {Object[]} Products in the { id, quantity, variations, extra, booking } shape.
 * @throws {Error} When the product form cannot be found.
 */
function getProductsFromForm() {
	// Classic themes render form.cart, block themes
	// form.wc-block-add-to-cart-with-options; locate via the field.
	const idElement =
		document.querySelector( 'form [name="add-to-cart"]' ) ||
		document.querySelector( 'form [name="product_id"]' );
	const form = idElement?.closest( 'form' );
	if ( ! form ) {
		throw new Error( 'Product form not found.' );
	}

	const handler = new SingleProductActionHandler( null, null, form, null );

	return handler.getProducts().map( ( product ) => product.data() );
}

/**
 * Creates a PayPal order via the existing WC AJAX endpoints.
 *
 * On product pages the viewed product is first added to the cart
 * (ppc-change-cart), matching the v5 flow; the returned purchase units
 * are passed to ppc-create-order, which derives the product-context
 * return URL from them.
 *
 * @param {Object} config        - The wc_ppcp_sdk_v6 config object.
 * @param {string} context       - The page context.
 * @param {string} fundingSource - The funding source (paypal, venmo, paylater).
 * @return {Promise<{orderId: string}>} The created PayPal order id.
 */
export async function createOrder( config, context, fundingSource ) {
	let purchaseUnits = [];
	if ( context === 'product' ) {
		purchaseUnits = await postJson( config.ajax.change_cart, {
			products: getProductsFromForm(),
		} );
	}

	const data = await postJson( config.ajax.create_order, {
		context,
		purchase_units: purchaseUnits,
		payment_method: 'ppcp-gateway',
		funding_source: fundingSource || 'paypal',
		save_order_in_session: 1,
	} );

	return { orderId: data.id };
}

/**
 * Approves the order and continues the purchase.
 *
 * Mirrors the v5 classic continuation flow (onApproveForContinue): the
 * endpoint stores the approved order in the WC session, and on
 * product/cart contexts the buyer is redirected to checkout, where the
 * gateway processes the session order on Place Order. (Creating the WC
 * order directly, should_create_wc_order, is the blocks express flow —
 * it requires a shipping option selected inside the popup, which a
 * One-Touch approval may never provide.) On classic checkout the WC
 * checkout form is submitted after approval instead.
 *
 * @param {Object} config        - The wc_ppcp_sdk_v6 config object.
 * @param {string} context       - The page context.
 * @param {string} fundingSource - The funding source used for payment.
 * @param {string} orderId       - The PayPal order ID.
 */
export async function approveOrder( config, context, fundingSource, orderId ) {
	await postJson( config.ajax.approve_order, {
		order_id: orderId,
		funding_source: fundingSource,
	} );

	if ( context === 'checkout' && typeof jQuery !== 'undefined' ) {
		const checkoutForm = jQuery( 'form.checkout' );
		if ( checkoutForm.length ) {
			// The approved order must be processed by the PayPal gateway,
			// not whichever payment method radio happens to be checked.
			const gatewayRadio = document.querySelector(
				'#payment_method_ppcp-gateway'
			);
			if ( gatewayRadio && ! gatewayRadio.checked ) {
				gatewayRadio.checked = true;
				jQuery( gatewayRadio ).trigger( 'change' );
			}

			checkoutForm.trigger( 'submit' );
			return;
		}
	}

	// Continuation: the buyer completes the order on the checkout page.
	window.location.assign( config.urls.checkout );
}

/**
 * Patches the PayPal order with totals recalculated from the WC cart.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} orderId - The PayPal order ID.
 * @return {Promise<void>} Resolves when the order has been patched.
 */
export async function updateShipping( config, orderId ) {
	await postJson( config.ajax.update_shipping, {
		order_id: orderId,
	} );
}

/**
 * Fetches the current cart total from the WC Store API, for refreshing
 * amount-sensitive eligibility (Pay Later thresholds) after cart changes.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {Promise<string>} The total as a decimal string, or '' on failure.
 */
export async function fetchCartTotal( config ) {
	try {
		const response = await fetch( config.ajax.wc_store_api.cart, {
			credentials: 'same-origin',
		} );
		const cart = await response.json();
		const minorUnit = cart?.totals?.currency_minor_unit ?? 2;
		const totalPrice = parseInt( cart?.totals?.total_price, 10 );

		if ( isNaN( totalPrice ) ) {
			return '';
		}

		return ( totalPrice / Math.pow( 10, minorUnit ) ).toFixed( minorUnit );
	} catch ( error ) {
		return '';
	}
}
