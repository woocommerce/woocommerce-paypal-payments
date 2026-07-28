/**
 * Converts a PayPal order (Orders v2 shape, as returned by ppc-get-order)
 * into WooCommerce billing/shipping address objects.
 *
 * Ported from the v5 blocks Helper/Address so the v6 module does not
 * source-depend on the ppcp-blocks module it replaces. Kept in sync with
 * the ppc-get-order response contract (the same shape v5 consumes).
 *
 * @package
 */

/**
 * Splits a full name into first and last parts.
 *
 * @param {string} fullName - The full name.
 * @return {[string, string]} The first and last name.
 */
function splitFullName( fullName ) {
	fullName = fullName.trim();
	if ( ! fullName.includes( ' ' ) ) {
		return [ fullName, '' ];
	}
	const parts = fullName.split( ' ' );
	const firstName = parts.shift();
	return [ firstName, parts.join( ' ' ) ];
}

/**
 * Maps a PayPal API address (snake_case Orders v2 fields) to WC fields.
 *
 * @param {Object} address - The PayPal address.
 * @return {Object} WC address fields.
 */
function paypalAddressToWc( address ) {
	const map = {
		country_code: 'country',
		address_line_1: 'address_1',
		address_line_2: 'address_2',
		admin_area_1: 'state',
		admin_area_2: 'city',
		postal_code: 'postcode',
	};

	const result = {};
	Object.entries( map ).forEach( ( [ paypalKey, wcKey ] ) => {
		if ( address?.[ paypalKey ] ) {
			result[ wcKey ] = address[ paypalKey ];
		}
	} );

	return {
		first_name: '',
		last_name: '',
		company: '',
		address_1: '',
		address_2: '',
		city: '',
		state: '',
		postcode: '',
		country: '',
		phone: '',
		...result,
	};
}

/**
 * Maps a PayPal order shipping block to a WC address.
 *
 * @param {Object} order - The PayPal order.
 * @return {Object} WC address fields.
 */
function paypalOrderToWcShippingAddress( order ) {
	const shipping = order?.purchase_units?.[ 0 ]?.shipping;
	if ( ! shipping ) {
		return {};
	}

	const [ firstName, lastName ] = shipping.name?.full_name
		? splitFullName( shipping.name.full_name )
		: [ '', '' ];

	return {
		...paypalAddressToWc( shipping.address ),
		first_name: firstName,
		last_name: lastName,
	};
}

/**
 * Maps a PayPal payer to a WC billing address.
 *
 * @param {Object} payer - The PayPal payer.
 * @return {Object} WC address fields.
 */
function paypalPayerToWc( payer ) {
	const address = payer?.address ? paypalAddressToWc( payer.address ) : {};
	return {
		...address,
		first_name: payer?.name?.given_name ?? '',
		last_name: payer?.name?.surname ?? '',
		email: payer?.email_address ?? '',
		phone: payer?.phone?.phone_number?.national_number ?? '',
	};
}

/**
 * Derives WC billing and shipping addresses from a PayPal order.
 *
 * @param {Object} order - The PayPal order (Orders v2 shape).
 * @return {{billingAddress: Object, shippingAddress: Object}} WC addresses.
 */
export function paypalOrderToWcAddresses( order ) {
	const shippingAddress = paypalOrderToWcShippingAddress( order );
	// A copy, not the same reference: callers dispatch these as separate
	// billing/shipping payloads and must be able to treat them independently.
	let billingAddress = { ...shippingAddress };

	if ( order?.payer ) {
		billingAddress = paypalPayerToWc( order.payer );
		// No billing address (e.g. billing retrieval not allowed): keep the
		// shipping address, overlaid with any non-empty payer fields so an
		// empty payer country does not blank out the shipping country.
		if ( ! billingAddress.address_1 ) {
			const payerFields = Object.fromEntries(
				Object.entries( billingAddress ).filter(
					( [ key, value ] ) => value !== '' && key !== 'country'
				)
			);
			billingAddress = { ...shippingAddress, ...payerFields };
		}
	}

	return { billingAddress, shippingAddress };
}
