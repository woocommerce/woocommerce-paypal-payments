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
 * Approves the order and continues the purchase.
 *
 * Mirrors the v5 flow (onApproveForContinue): should_create_wc_order is
 * requested like in v5 (except for Venmo with vaulting enabled), and the
 * server decides — with the Pay Now experience enabled it creates the WC
 * order right away and responds with order_received_url, skipping the
 * Order Review page. Otherwise the endpoint only stores the approved
 * order in the WC session and the buyer continues on checkout, where the
 * gateway processes the session order on Place Order (also the fallback
 * when order creation is not possible, e.g. a One-Touch approval without
 * a shipping option selected inside the popup). On classic checkout the
 * WC checkout form is submitted after approval instead.
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

	// Continuation: the buyer completes the order on the checkout page.
	navigation.assign( config.urls.checkout );
}

/**
 * Creates a PayPal order for the Advanced Card Fields (ACDC) checkout flow.
 *
 * Unlike createOrder(), this never sets save_order_in_session: at
 * create-order time the order has no card attached yet, no 3D Secure
 * decision exists, and the disabled-card-brand check hasn't run — storing
 * it in the session this early would let the native checkout capture an
 * unconfirmed, cardless order. approveCardOrder() is what stores the
 * order in session, once those checks pass.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {Promise<{orderId: string}>} The created PayPal order id.
 */
export async function createCardOrder( config ) {
	const body = {
		context: 'checkout',
		purchase_units: [],
		payment_method: config.card_fields.payment_method,
		funding_source: config.card_fields.funding_source,
	};

	const form = document.querySelector( 'form.checkout' );
	if ( form ) {
		body.form_encoded = new URLSearchParams(
			new FormData( form )
		).toString();
		body.createaccount = !! form.querySelector( '#createaccount' )?.checked;
	}

	const payer = payerData();
	if ( payer ) {
		body.payer = payer;
	}

	const data = await postJson( config.ajax.create_order, body );

	return { orderId: data.id };
}

/**
 * Approves a card-fields order after the card session has confirmed it.
 *
 * Mirrors the v5 ACDC flow's card branch: the endpoint runs the
 * disabled-card-brand and 3D Secure checks, then stores the confirmed
 * order in the WC session so the native checkout submission (triggered
 * right after this resolves) can capture it via the existing
 * CreditCardGateway::process_payment() flow.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} orderId - The PayPal order ID.
 */
export async function approveCardOrder( config, orderId ) {
	await postJson( config.ajax.approve_order, {
		order_id: orderId,
		funding_source: config.card_fields.funding_source,
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
