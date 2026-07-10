/**
 * Handles shipping address and option changes inside the PayPal popup.
 *
 * @package
 */

/**
 * Converts a PayPal v6 shipping address to WC format.
 *
 * @param {Object} address - The PayPal shipping address.
 * @return {Object} WC-formatted address fields.
 */
function paypalAddressToWc( address ) {
	return {
		country: address.countryCode || '',
		state: address.state || '',
		postcode: address.postalCode || '',
		city: address.city || '',
	};
}

/**
 * Updates the WC customer shipping address via the Store API,
 * then patches the PayPal order with recalculated totals.
 *
 * @param {Object} data   - The v6 onShippingAddressChange data.
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 */
export async function handleShippingAddressChange( data, config ) {
	const storeApi = config.ajax.wc_store_api;
	const address = paypalAddressToWc( data.shippingAddress );

	// Fetch current cart data from Store API.
	const cartRes = await fetch( storeApi.cart, {
		credentials: 'same-origin',
	} );
	const cartData = await cartRes.json();

	// Merge the new address into the cart shipping address.
	cartData.shipping_address.country = address.country;
	cartData.shipping_address.state = address.state;
	cartData.shipping_address.postcode = address.postcode;
	cartData.shipping_address.city = address.city;

	// Update customer via Store API.
	await fetch( storeApi.update_customer, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			Nonce: storeApi.nonce,
		},
		body: JSON.stringify( {
			shipping_address: cartData.shipping_address,
		} ),
	} );

	// Patch the PayPal order with recalculated totals.
	const res = await fetch( config.ajax.update_shipping.endpoint, {
		method: 'POST',
		credentials: 'same-origin',
		body: JSON.stringify( {
			nonce: config.ajax.update_shipping.nonce,
			order_id: data.orderId,
		} ),
	} );

	const json = await res.json();
	if ( ! json.success ) {
		data.errors.addError( json.data?.message || 'Shipping update failed.' );
	}
}

/**
 * Updates the selected shipping option in WC via the Store API,
 * then patches the PayPal order with recalculated totals.
 *
 * @param {Object} data   - The v6 onShippingOptionsChange data.
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 */
export async function handleShippingOptionsChange( data, config ) {
	const storeApi = config.ajax.wc_store_api;
	const shippingOptionId = data.selectedShippingOption?.id;

	if ( shippingOptionId ) {
		// Select the shipping rate via Store API.
		await fetch( storeApi.select_shipping_rate, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				Nonce: storeApi.nonce,
			},
			body: JSON.stringify( {
				rate_id: shippingOptionId,
			} ),
		} );
	}

	// Patch the PayPal order with recalculated totals.
	const res = await fetch( config.ajax.update_shipping.endpoint, {
		method: 'POST',
		credentials: 'same-origin',
		body: JSON.stringify( {
			nonce: config.ajax.update_shipping.nonce,
			order_id: data.orderId,
		} ),
	} );

	const json = await res.json();
	if ( ! json.success ) {
		data.errors.addError( json.data?.message || 'Shipping update failed.' );
	}
}
