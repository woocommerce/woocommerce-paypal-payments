/**
 * @param {string} fullName
 * @return {Array}
 */
export const splitFullName = ( fullName ) => {
	fullName = fullName.trim();
	if ( ! fullName.includes( ' ' ) ) {
		return [ fullName, '' ];
	}
	const parts = fullName.split( ' ' );
	const firstName = parts[ 0 ];
	parts.shift();
	const lastName = parts.join( ' ' );
	return [ firstName, lastName ];
};

/**
 * @param {Object} address
 * @return {Object}
 */
export const paypalAddressToWc = ( address ) => {
	let map = {
		country_code: 'country',
		address_line_1: 'address_1',
		address_line_2: 'address_2',
		admin_area_1: 'state',
		admin_area_2: 'city',
		postal_code: 'postcode',
	};
	if ( address.city ) {
		// address not from API, such as onShippingChange
		map = {
			country_code: 'country',
			state: 'state',
			city: 'city',
			postal_code: 'postcode',
		};
	}
	const result = {};
	Object.entries( map ).forEach( ( [ paypalKey, wcKey ] ) => {
		if ( address[ paypalKey ] ) {
			result[ wcKey ] = address[ paypalKey ];
		}
	} );

	const defaultAddress = {
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
	};

	return { ...defaultAddress, ...result };
};

/**
 * @param {Object} shipping
 * @return {Object}
 */
export const paypalShippingToWc = ( shipping ) => {
	const [ firstName, lastName ] = shipping.name
		? splitFullName( shipping.name.full_name )
		: [ '', '' ];
	return {
		...paypalAddressToWc( shipping.address ),
		first_name: firstName,
		last_name: lastName,
	};
};

/**
 * @param {Object} payer
 * @return {Object}
 */
export const paypalPayerToWc = ( payer ) => {
	const firstName = payer?.name?.given_name ?? '';
	const lastName = payer?.name?.surname ?? '';
	const address = payer.address ? paypalAddressToWc( payer.address ) : {};
	return {
		...address,
		first_name: firstName,
		last_name: lastName,
		email: payer.email_address,
	};
};

/**
 * @param {Object} subscriber
 * @return {Object}
 */
export const paypalSubscriberToWc = ( subscriber ) => {
	const firstName = subscriber?.name?.given_name ?? '';
	const lastName = subscriber?.name?.surname ?? '';
	const address = subscriber.address
		? paypalAddressToWc( subscriber.shipping_address.address )
		: {};
	return {
		...address,
		first_name: firstName,
		last_name: lastName,
		email: subscriber.email_address,
	};
};

/**
 * @param {Object} order
 * @return {Object}
 */
export const paypalOrderToWcShippingAddress = ( order ) => {
	const shipping = order.purchase_units[ 0 ].shipping;
	if ( ! shipping ) {
		return {};
	}

	const res = paypalShippingToWc( shipping );

	// use the name from billing if the same, to avoid possible mistakes when splitting full_name
	if ( order.payer ) {
		const billingAddress = paypalPayerToWc( order.payer );
		if (
			`${ res.first_name } ${ res.last_name }` ===
			`${ billingAddress.first_name } ${ billingAddress.last_name }`
		) {
			res.first_name = billingAddress.first_name;
			res.last_name = billingAddress.last_name;
		}
	}

	return res;
};

/**
 *
 * @param  order
 * @return {{shippingAddress: Object, billingAddress: Object}}
 */
export const paypalOrderToWcAddresses = ( order ) => {
	const shippingAddress = paypalOrderToWcShippingAddress( order );
	let billingAddress = shippingAddress;
	if ( order.payer ) {
		billingAddress = paypalPayerToWc( order.payer );
		// no billing address, such as if billing address retrieval is not allowed in the merchant account
		if ( ! billingAddress.address_line_1 ) {
			// use only non empty values from payer address, otherwise it will override shipping address
			const payerAddress = Object.fromEntries(
				Object.entries( billingAddress ).filter(
					( [ key, value ] ) => value !== '' && key !== 'country'
				)
			);

			billingAddress = {
				...shippingAddress,
				...payerAddress,
			};
		}
	}

	return { billingAddress, shippingAddress };
};

/**
 *
 * @param  subscription
 * @return {{shippingAddress: Object, billingAddress: Object}}
 */
export const paypalSubscriptionToWcAddresses = ( subscription ) => {
	const shippingAddress = paypalSubscriberToWc( subscription.subscriber );
	const billingAddress = shippingAddress;
	return { billingAddress, shippingAddress };
};

/**
 * Merges two WC addresses.
 * The objects can contain either the WC form fields or billingAddress, shippingAddress objects.
 *
 * @param {Object} address1
 * @param {Object} address2
 * @return {any}
 */
export const mergeWcAddress = ( address1, address2 ) => {
	if ( 'billingAddress' in address1 ) {
		return {
			billingAddress: mergeWcAddress(
				address1.billingAddress,
				address2.billingAddress
			),
			shippingAddress: mergeWcAddress(
				address1.shippingAddress,
				address2.shippingAddress
			),
		};
	}

	const address2WithoutEmpty = { ...address2 };
	Object.keys( address2 ).forEach( ( key ) => {
		if ( address2[ key ] === '' ) {
			delete address2WithoutEmpty[ key ];
		}
	} );

	return { ...address1, ...address2WithoutEmpty };
};
