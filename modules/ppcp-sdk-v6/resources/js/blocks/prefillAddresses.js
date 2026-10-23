/**
 * Pushes a PayPal order's addresses into the WooCommerce cart.
 *
 * @package
 */

import {
	paypalOrderToWcAddresses,
	paypalSubscriberToWcAddresses,
} from './address';

/**
 * Merges incoming fields over existing ones, ignoring empty incoming values.
 *
 * paypalOrderToWcAddresses() pads with empty strings, so a straight overwrite
 * would blank a stored company or phone the PayPal order does not carry.
 *
 * @param {Object} existing - The current WC address.
 * @param {Object} incoming - The PayPal-derived address.
 * @return {Object} The merged address.
 */
function mergeAddress( existing = {}, incoming = {} ) {
	const populated = Object.fromEntries(
		Object.entries( incoming ).filter( ( [ , value ] ) => value !== '' )
	);

	return { ...existing, ...populated };
}

/**
 * Persists the buyer's PayPal addresses to the WC cart, optionally reflecting
 * them in the Blocks UI.
 *
 * @param {Object}  order                  - The PayPal order (Orders v2 shape).
 * @param {Object}  [options]              - Options.
 * @param {boolean} [options.needsShipping] - Whether the cart ships.
 * @param {boolean} [options.reflectInUi]   - Also push into the Blocks form state.
 * @return {Promise<void>} Resolves once the cart has been updated.
 */
export async function prefillFromPayPalOrder( order, options ) {
	await prefillAddresses( paypalOrderToWcAddresses( order ), options );
}

/**
 * Persists a PayPal subscriber's address to the WC cart, optionally reflecting
 * it in the Blocks UI.
 *
 * @param {Object}  subscriber              - The subscriber (Subscriptions v1 shape).
 * @param {Object}  [options]               - Options.
 * @param {boolean} [options.needsShipping] - Whether the cart ships.
 * @param {boolean} [options.reflectInUi]   - Also push into the Blocks form state.
 * @return {Promise<void>} Resolves once the cart has been updated.
 */
export async function prefillFromPayPalSubscriber( subscriber, options ) {
	await prefillAddresses(
		paypalSubscriberToWcAddresses( subscriber ),
		options
	);
}

/**
 * Merges PayPal-derived addresses over the cart's and stores them.
 *
 * @param {Object}  paypal                  - The PayPal-derived { billingAddress, shippingAddress }.
 * @param {Object}  [options]               - Options.
 * @param {boolean} [options.needsShipping] - Whether the cart ships.
 * @param {boolean} [options.reflectInUi]   - Also push into the Blocks form state.
 * @return {Promise<void>} Resolves once the cart has been updated.
 */
async function prefillAddresses(
	paypal,
	{ needsShipping = false, reflectInUi = false } = {}
) {
	const current = wp.data.select( 'wc/store/cart' )?.getCustomerData?.() || {};

	const billingAddress = mergeAddress(
		current.billingAddress,
		paypal.billingAddress
	);
	const shippingAddress = mergeAddress(
		current.shippingAddress,
		paypal.shippingAddress
	);

	const cart = wp.data.dispatch( 'wc/store/cart' );

	const customerData = { billing_address: billingAddress };
	if ( needsShipping ) {
		customerData.shipping_address = shippingAddress;
	}
	await cart.updateCustomerData( customerData );

	// The Store API response strips billing/shipping, so the form only picks
	// these up if we set them locally too.
	if ( reflectInUi ) {
		cart.setBillingAddress( billingAddress );
		if ( needsShipping ) {
			cart.setShippingAddress( shippingAddress );
		}
	}
}
