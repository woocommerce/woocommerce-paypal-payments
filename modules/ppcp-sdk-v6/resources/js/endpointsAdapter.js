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
import { payerData } from '@ppcp-button/Helper/PayerData';
import { postJson } from './utils/api';
import { minorUnitsToDecimal } from './utils/amount';
import { continuationRedirectUrl } from './utils/continuation';

/**
 * Navigation seam: window.location is not mockable under jsdom, so
 * redirects go through this indirection to stay unit-testable.
 */
export const navigation = {
	assign: ( url ) => window.location.assign( url ),
};

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

	const body = {
		context,
		purchase_units: purchaseUnits,
		payment_method: 'ppcp-gateway',
		funding_source: fundingSource || 'paypal',
		save_order_in_session: 1,
	};

	if ( context === 'checkout' ) {
		// Mirrors the v5 CheckoutActionHandler: the serialized form lets
		// the server run the early WC checkout validation before creating
		// the order, so the buyer sees form errors before approving.
		const form = document.querySelector( 'form.checkout' );
		if ( form ) {
			body.form_encoded = new URLSearchParams(
				new FormData( form )
			).toString();
			body.createaccount =
				!! form.querySelector( '#createaccount' )?.checked;
		}

		const payer = payerData();
		if ( payer ) {
			body.payer = payer;
		}
	}

	const data = await postJson( config.ajax.create_order, body );

	return { orderId: data.id };
}

/**
 * Mirrors the v5 flow (onApproveForContinue): should_create_wc_order is
 * requested except for Venmo with vaulting, and the server decides. With the
 * Pay Now experience it creates the WC order and responds with
 * order_received_url; otherwise it only stores the approved order in the
 * session and the gateway processes it on Place Order. On classic checkout
 * the WC checkout form is submitted after approval instead.
 *
 * @param {Object} config        - The wc_ppcp_sdk_v6 config object.
 * @param {string} context       - The page context.
 * @param {string} fundingSource - The funding source used for payment.
 * @param {string} orderId       - The PayPal order ID.
 */
export async function approveOrder( config, context, fundingSource, orderId ) {
	const canCreateOrder =
		! config.vaulting_enabled || fundingSource !== 'venmo';

	let data;
	try {
		data = await postJson( config.ajax.approve_order, {
			order_id: orderId,
			funding_source: fundingSource,
			should_create_wc_order: canCreateOrder,
		} );
	} catch ( error ) {
		if ( ! canCreateOrder ) {
			throw error;
		}
		// e.g. One-Touch approval without a shipping option; fall back
		// to the classic continuation on checkout.
		data = await postJson( config.ajax.approve_order, {
			order_id: orderId,
			funding_source: fundingSource,
			should_create_wc_order: false,
		} );
	}

	if ( data?.order_received_url ) {
		navigation.assign( data.order_received_url );
		return;
	}

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

	// Continuation: the buyer completes the order on the checkout page. Cache-
	// busted because a cached checkout would carry no continuation payload and
	// show the express buttons again for an already-approved order.
	navigation.assign( continuationRedirectUrl( config ) );
}

/**
 * Fetches the full PayPal order (ppc-get-order).
 *
 * Used by the block express flow to read the buyer's PayPal address,
 * which the v6 session onApprove does not provide.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} orderId - The PayPal order ID.
 * @return {Promise<Object>} The PayPal order (Orders v2 shape).
 */
export async function getOrder( config, orderId ) {
	return postJson( config.ajax.get_order, {
		order_id: orderId,
	} );
}

/**
 * Approves the order and stores it in the WC session without creating the
 * WC order or redirecting.
 *
 * The block checkout submit creates the WC order through the gateway, so
 * unlike the classic approveOrder this must not create it or navigate away.
 *
 * @param {Object} config        - The wc_ppcp_sdk_v6 config object.
 * @param {string} fundingSource - The funding source used for payment.
 * @param {string} orderId       - The PayPal order ID.
 * @return {Promise<void>} Resolves when the order has been approved.
 */
export async function approveOrderInSession( config, fundingSource, orderId ) {
	await postJson( config.ajax.approve_order, {
		order_id: orderId,
		funding_source: fundingSource,
		should_create_wc_order: false,
	} );
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

		return minorUnitsToDecimal(
			cart?.totals?.total_price,
			cart?.totals?.currency_minor_unit
		);
	} catch ( error ) {
		return '';
	}
}
