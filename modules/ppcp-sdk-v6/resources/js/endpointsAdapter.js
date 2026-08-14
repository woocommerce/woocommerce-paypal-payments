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
import { FundingSources } from './utils/fundingSources';
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
 * Adds the viewed product to the cart (ppc-change-cart).
 *
 * Empties the cart first, so a repeat call is safe. The server validates the
 * product as part of this, so an invalid one fails before any order exists.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {Promise<Object[]>} The resulting purchase units.
 */
export async function changeCart( config ) {
	return postJson( config.ajax.change_cart, {
		products: getProductsFromForm(),
	} );
}

/**
 * Creates a PayPal order via the existing WC AJAX endpoints.
 *
 * On product pages the viewed product is first added to the cart
 * (ppc-change-cart), matching the v5 flow; the returned purchase units
 * are passed to ppc-create-order, which derives the product-context
 * return URL from them.
 *
 * @param {Object}   config          - The wc_ppcp_sdk_v6 config object.
 * @param {string}   context         - The page context.
 * @param {string}   fundingSource   - The funding source (paypal, venmo, paylater).
 * @param {Object[]} [purchaseUnits] - Units the caller already resolved. Wallets
 *                                     pass theirs so the cart is not changed twice.
 * @param {string}   [paymentMethod] - The WC gateway that processes the order.
 * @return {Promise<{orderId: string}>} The created PayPal order id.
 */
export async function createOrder(
	config,
	context,
	fundingSource,
	purchaseUnits,
	paymentMethod = 'ppcp-gateway'
) {
	const units =
		purchaseUnits ??
		( context === 'product' ? await changeCart( config ) : [] );

	const body = {
		context,
		purchase_units: units,
		payment_method: paymentMethod,
		funding_source: fundingSource || FundingSources.PAYPAL,
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
 * @param {Object} config                     - The wc_ppcp_sdk_v6 config object.
 * @param {string} context                    - The page context.
 * @param {string} fundingSource              - The funding source used for payment.
 * @param {string} orderId                    - The PayPal order ID.
 * @param {Object} [contact]                  - Buyer contact data from a wallet sheet.
 * @param {Object} [contact.payer]            - The PayPal payer (Orders v2 shape).
 * @param {Object} [contact.shippingAddress]  - The PayPal shipping address.
 * @param {string} [paymentMethod]            - The WC gateway that processes
 *                                              the order.
 */
export async function approveOrder(
	config,
	context,
	fundingSource,
	orderId,
	contact = {},
	paymentMethod = 'ppcp-gateway'
) {
	const canCreateOrder =
		! config.vaulting_enabled || fundingSource !== FundingSources.VENMO;

	const body = {
		order_id: orderId,
		funding_source: fundingSource,
		should_create_wc_order: canCreateOrder,
	};

	// Only useful while this request creates the WC order: the retry below
	// leaves that to the gateway, which reads the addresses off the WC session.
	if ( canCreateOrder ) {
		if ( contact.payer ) {
			body.payer = contact.payer;
		}
		if ( contact.shippingAddress ) {
			body.shipping_address = contact.shippingAddress;
		}
	}

	let data;
	try {
		data = await postJson( config.ajax.approve_order, body );
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
			// The approved order must be processed by the gateway that created
			// it, not whichever payment method radio happens to be checked. On
			// the express path that means switching to PayPal; where the wallet
			// is its own gateway the buyer already selected it, so this is a
			// no-op.
			const gatewayRadio = document.querySelector(
				`#payment_method_${ paymentMethod }`
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

		return minorUnitsToDecimal(
			cart?.totals?.total_price,
			cart?.totals?.currency_minor_unit
		);
	} catch ( error ) {
		return '';
	}
}
