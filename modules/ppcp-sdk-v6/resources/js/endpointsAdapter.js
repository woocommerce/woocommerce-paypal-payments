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
 * Submits the pay-for-order form after selecting the PayPal gateway, so the
 * approved order is captured by the PayPal gateway (not whichever method radio
 * happens to be checked) and WC issues the order-received redirect.
 *
 * @throws {Error} When jQuery or the pay-order form is unavailable.
 */
function submitPayOrderForm() {
	if ( typeof jQuery === 'undefined' ) {
		// eslint-disable-next-line no-console
		console.error(
			'[ppcp-sdk-v6] cannot submit pay-for-order: jQuery is unavailable on this page.'
		);
		throw new Error( 'Could not submit the order.' );
	}

	const form = jQuery( 'form#order_review' );
	if ( ! form.length ) {
		// eslint-disable-next-line no-console
		console.error(
			'[ppcp-sdk-v6] cannot submit pay-for-order: form#order_review was not found in the DOM.'
		);
		throw new Error( 'Order form not found.' );
	}

	const gatewayRadio = document.querySelector(
		'#payment_method_ppcp-gateway'
	);
	if ( gatewayRadio && ! gatewayRadio.checked ) {
		gatewayRadio.checked = true;
		jQuery( gatewayRadio ).trigger( 'change' );
	}

	form.trigger( 'submit' );
}

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

	// Pay-for-order: the server builds the order from the existing WC order,
	// identified by these, rather than from the cart.
	if ( context === 'pay-now' && config.pay_now ) {
		body.order_id = config.pay_now.order_id;
		body.order_key = config.pay_now.order_key;
	}

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
	// Pay-for-order: the WC order already exists. Approve it into the session
	// (never request WC-order creation — that would create a duplicate, since
	// is_checkout() is false during this AJAX call) and submit the pay-order
	// form so the PayPal gateway captures the existing order and redirects to
	// the order-received page.
	if ( context === 'pay-now' ) {
		await approveOrderInSession( config, fundingSource, orderId );
		submitPayOrderForm();
		return;
	}

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
 * Creates a PayPal order for the Advanced Card Fields (ACDC) checkout flow.
 *
 * Unlike createOrder(), this never sets save_order_in_session: at
 * create-order time the order has no card attached yet, no 3D Secure
 * decision exists, and the disabled-card-brand check hasn't run — storing
 * it in the session this early would let the native checkout capture an
 * unconfirmed, cardless order. approveCardOrder() is what stores the
 * order in session, once those checks pass.
 *
 * @param {Object} config   - The wc_ppcp_sdk_v6 config object.
 * @param {string} context  - The page context (checkout or checkout-block).
 * @param {string} cardName - The cardholder name (v6 has no name field component).
 * @return {Promise<{orderId: string}>} The created PayPal order id.
 */
export async function createCardOrder(
	config,
	context = 'checkout',
	cardName = ''
) {
	const body = {
		context,
		purchase_units: [],
		payment_method: config.card_fields.payment_method,
		funding_source: config.card_fields.funding_source,
	};

	// The v6 card-fields component set is number|expiry|cvv only, so the
	// cardholder name is collected as a plain input and sent to the server,
	// which sets it as payment_source.card.name on the order.
	if ( cardName ) {
		body.card_name = cardName;
	}

	// Pay-for-order: the server builds the order from the existing WC order.
	if ( context === 'pay-now' && config.pay_now ) {
		body.order_id = config.pay_now.order_id;
		body.order_key = config.pay_now.order_key;
	}

	// Only the classic checkout has a WC form to serialize, letting the server
	// run its early validation before creating the order; other contexts submit
	// their data separately.
	if ( context === 'checkout' ) {
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

		return minorUnitsToDecimal(
			cart?.totals?.total_price,
			cart?.totals?.currency_minor_unit
		);
	} catch ( error ) {
		return '';
	}
}
