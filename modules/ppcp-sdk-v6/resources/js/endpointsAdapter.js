/**
 * Adapter for the WC AJAX order endpoints this module shares with the classic
 * button stack.
 *
 * Every assumption about that contract lives here: ppc-change-cart,
 * ppc-create-order, ppc-approve-order, ppc-update-shipping. Keep the
 * request/response shapes in sync with the endpoint contract tests.
 */

import SingleProductActionHandler from '@ppcp-button/ActionHandler/SingleProductActionHandler';
import { payerData } from '@ppcp-button/Helper/PayerData';
import { postJson, postStoreApi } from './utils/api';
import { describeError, logError } from './utils/diagnostics';
import { FundingSources } from './utils/fundingSources';
import { amountFromCartTotals } from './utils/amount';
import { continuationRedirectUrl } from './utils/continuation';

// The gateway that processes an order unless a caller names another one.
const DEFAULT_PAYMENT_METHOD = 'ppcp-gateway';

/**
 * Navigation seam: window.location is not mockable under jsdom, so
 * redirects go through this indirection to stay unit-testable.
 */
export const navigation = {
	assign: ( url ) => window.location.assign( url ),
};

/**
 * Submits the pay-for-order form after selecting the paying gateway, so the
 * approved order is captured by that gateway (not whichever method radio
 * happens to be checked) and WC issues the order-received redirect.
 *
 * @param {string} paymentMethod - The WC gateway that processes the order.
 * @throws {Error} When jQuery or the pay-order form is unavailable.
 */
function submitPayOrderForm( paymentMethod = DEFAULT_PAYMENT_METHOD ) {
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
		`#payment_method_${ paymentMethod }`
	);
	if ( gatewayRadio && ! gatewayRadio.checked ) {
		gatewayRadio.checked = true;
		jQuery( gatewayRadio ).trigger( 'change' );
	}

	form.trigger( 'submit' );
}

/**
 * The form describing the viewed product.
 *
 * Exported because the Apple Pay sheet total watches this same form for changes,
 * and the two must not disagree about which one it is.
 *
 * @return {?HTMLElement} The form, or null when the page has none.
 */
export function productForm() {
	// Classic themes render form.cart, block themes
	// form.wc-block-add-to-cart-with-options; locate via the field.
	const idElement =
		document.querySelector( 'form [name="add-to-cart"]' ) ||
		document.querySelector( 'form [name="product_id"]' );

	return idElement?.closest( 'form' ) ?? null;
}

/**
 * Collects products from the single product form for ppc-change-cart.
 *
 * Reuses SingleProductActionHandler, which handles simple, variable, grouped and
 * booking products plus extra third-party form fields.
 *
 * @return {Object[]} Products in the { id, quantity, variations, extra, booking } shape.
 * @throws {Error} When the product form cannot be found.
 */
function getProductsFromForm() {
	const form = productForm();
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
 * Prices the viewed product without touching the cart (ppc-sdk-v6-simulate-cart).
 *
 * For totals that must be known before the shopper acts, which changeCart() cannot
 * answer because it adds the product to the real cart.
 *
 * TODO (phase 3): product-page Pay Later eligibility still uses the localized
 * config.amount, which is the cart total whenever the cart is not empty, and
 * Google Pay still resolves its sheet total by mutating the cart on click. Both
 * should read this instead.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {Promise<{total: string, currency_code: string}>} The simulated total.
 */
export async function simulateCart( config ) {
	return postJson( config.ajax.simulate_cart, {
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
 *                                   pass theirs so the cart is not changed twice.
 * @param {string}   [paymentMethod] - The WC gateway that processes the order.
 * @return {Promise<{orderId: string}>} The created PayPal order id.
 */
export async function createOrder(
	config,
	context,
	fundingSource,
	purchaseUnits,
	paymentMethod = DEFAULT_PAYMENT_METHOD
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

	// Pay-for-order: the server builds the order from the existing WC order,
	// identified by these, rather than from the cart.
	if ( context === 'pay-now' && config.pay_now ) {
		body.order_id = config.pay_now.order_id;
		body.order_key = config.pay_now.order_key;
	}

	if ( context === 'checkout' ) {
		// The serialized form lets the server run the early WC checkout
		// validation before creating the order, so the buyer sees form errors
		// before approving.
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
 * Reports an approved PayPal order and takes the buyer wherever it leads.
 *
 * should_create_wc_order is requested except for the card button and for Venmo
 * with vaulting, and the server decides: with the Pay Now experience it creates
 * the WC order and responds with order_received_url, otherwise it only stores the
 * approved order in the session and the gateway processes it on Place Order. On
 * classic checkout the WC checkout form is submitted after approval instead.
 *
 * @param {Object} config                    - The wc_ppcp_sdk_v6 config object.
 * @param {string} context                   - The page context.
 * @param {string} fundingSource             - The funding source used for payment.
 * @param {string} orderId                   - The PayPal order ID.
 * @param {Object} [contact]                 - Buyer contact data from a wallet sheet.
 * @param {Object} [contact.payer]           - The PayPal payer (Orders v2 shape).
 * @param {Object} [contact.shippingAddress] - The PayPal shipping address.
 * @param {string} [paymentMethod]           - The WC gateway that processes
 *                                           the order.
 */
export async function approveOrder(
	config,
	context,
	fundingSource,
	orderId,
	contact = {},
	paymentMethod = DEFAULT_PAYMENT_METHOD
) {
	// Pay-for-order: the WC order already exists. Approve it into the session
	// (never request WC-order creation — that would create a duplicate, since
	// is_checkout() is false during this AJAX call) and submit the pay-order
	// form so the paying gateway captures the existing order and redirects to
	// the order-received page.
	if ( context === 'pay-now' ) {
		await approveOrderInSession( config, fundingSource, orderId );
		submitPayOrderForm( paymentMethod );
		return;
	}

	// Never for the card button: ApproveOrderEndpoint would run
	// PayPalGateway::process_payment() and pin chosen_payment_method to the
	// PayPal gateway, so CardButtonGateway::process_payment() is never reached.
	// False routes us through the classic form submit below instead.
	const canCreateOrder =
		fundingSource !== FundingSources.CARD &&
		( ! config.vaulting_enabled ||
			fundingSource !== FundingSources.VENMO );

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

		// The retry below hides the refusal, so record it first.
		logError(
			config,
			'approve-order-retried',
			`${ fundingSource } order=${ orderId } ${ describeError( error ) }`
		);

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

			selectShippingMethodInForm( contact.shippingRateId );

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
 * @param {boolean} savePaymentMethod - Whether to vault the card during purchase.
 * @return {Promise<{orderId: string}>} The created PayPal order id.
 */
export async function createCardOrder(
	config,
	context = 'checkout',
	cardName = '',
    savePaymentMethod = false
) {
	const body = {
		context,
		purchase_units: [],
		payment_method: config.card_fields.payment_method,
		funding_source: config.card_fields.funding_source,
        save_payment_method: savePaymentMethod,
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
 * The endpoint runs the disabled-card-brand and 3D Secure checks, then stores
 * the confirmed
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
 * Fetches the current cart from the WC Store API.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {Promise<?Object>} The cart, or null when it could not be read.
 */
export async function fetchCart( config ) {
	try {
		const response = await fetch( config.ajax.wc_store_api.cart, {
			credentials: 'same-origin',
		} );

		return await response.json();
	} catch ( error ) {
		return null;
	}
}

/**
 * Fetches the current cart total from the WC Store API, for refreshing
 * amount-sensitive eligibility (Pay Later thresholds) after cart changes.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {Promise<string>} The total as a decimal string, or '' on failure.
 */
export async function fetchCartTotal( config ) {
	const cart = await fetchCart( config );

	return amountFromCartTotals( cart?.totals );
}

/**
 * Writes the shopper's shipping address to the WC customer.
 *
 * The Store API merges partial addresses server-side and answers with the
 * recalculated cart, so the caller learns the new totals and rates from the
 * same request.
 *
 * @param {Object} config  - The wc_ppcp_sdk_v6 config object.
 * @param {Object} address - WC address fields.
 * @return {Promise<?Object>} The recalculated cart.
 */
export async function updateCustomerAddress( config, address ) {
	const storeApi = config.ajax.wc_store_api;

	return postStoreApi( storeApi, storeApi.update_customer, {
		shipping_address: address,
	} );
}

/**
 * Points the checkout form's shipping-method field at the sheet's rate.
 *
 * The form submits its own shipping_method, and that beats the session. Assigned
 * rather than clicked: a click fires update_checkout, which posts the form's own
 * address back over the one the sheet just set.
 *
 * @param {?string} rateId - The WC rate id, e.g. flat_rate:3.
 */
function selectShippingMethodInForm( rateId ) {
	if ( ! rateId ) {
		return;
	}

	const input = document.querySelector(
		`input[name^="shipping_method"][value="${ rateId.replace(
			/"/g,
			''
		) }"]`
	);

	if ( input ) {
		input.checked = true;
	}
}

/**
 * Prices the cart for an address and rate chosen in a wallet payment sheet.
 *
 * One request, because WooCommerce re-picks the shipping method on every
 * recalculation, so setting the destination and the rate separately loses the
 * shopper's choice. Its total is the one ppc-create-order will charge.
 *
 * @param {Object}  config                  - The wc_ppcp_sdk_v6 config object.
 * @param {Object}  args                    - The selection.
 * @param {Object}  args.address            - WC shipping address fields, as
 *                                            complete as known.
 * @param {?string} [args.rateId]           - The rate the sheet selected.
 * @param {?Object} [args.billingAddress]   - The card's WC billing address, once
 *                                            authorization reveals it. Omitted
 *                                            leaves the customer's own untouched.
 * @param {?string} [args.expectedTotal]    - The total the sheet displayed. The
 *                                            server refuses a higher one rather
 *                                            than charge it.
 * @return {Promise<Object>} The quote.
 */
export async function quoteCartShipping(
	config,
	{ address, rateId = null, billingAddress = null, expectedTotal = null }
) {
	const body = {
		address,
		rate_id: rateId ?? '',
	};

	if ( billingAddress ) {
		body.billing_address = billingAddress;
	}

	if ( expectedTotal ) {
		body.expected_total = expectedTotal;
	}

	return postJson( config.ajax.wallet_shipping, body );
}

/**
 * Drops the server-side hold on the sheet's chosen rate.
 *
 * Called when the sheet closes without paying, so the shopper's own rate choices
 * apply again on the page behind it.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @return {Promise<?Object>} The endpoint's acknowledgement.
 */
export async function releaseCartShipping( config ) {
	return postJson( config.ajax.wallet_shipping, { release: true } );
}

/**
 * Selects a shipping rate on the WC cart.
 *
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 * @param {string} rateId - The WC rate id, e.g. flat_rate:3.
 * @return {Promise<?Object>} The recalculated cart.
 */
export async function selectShippingRate( config, rateId ) {
	const storeApi = config.ajax.wc_store_api;

	return postStoreApi( storeApi, storeApi.select_shipping_rate, {
		rate_id: rateId,
	} );
}
