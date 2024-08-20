/**
 * Name details.
 *
 * @typedef {Object} NameDetails
 * @property {?string} given_name - First name, e.g. "John".
 * @property {?string} surname    - Last name, e.g. "Doe".
 */

/**
 * Postal address details.
 *
 * @typedef {Object} AddressDetails
 * @property {undefined|string} country_code   - Country code (2-letter).
 * @property {undefined|string} address_line_1 - Address details, line 1 (street, house number).
 * @property {undefined|string} address_line_2 - Address details, line 2.
 * @property {undefined|string} admin_area_1   - State or region.
 * @property {undefined|string} admin_area_2   - State or region.
 * @property {undefined|string} postal_code    - Zip code.
 */

/**
 * Phone details.
 *
 * @typedef {Object} PhoneDetails
 * @property {undefined|string}                    phone_type   - Type, usually 'HOME'
 * @property {undefined|{national_number: string}} phone_number - Phone number details.
 */

/**
 * Payer details.
 *
 * @typedef {Object} PayerDetails
 * @property {undefined|string}         email_address - Email address for billing communication.
 * @property {undefined|PhoneDetails}   phone         - Phone number for billing communication.
 * @property {undefined|NameDetails}    name          - Payer's name.
 * @property {undefined|AddressDetails} address       - Postal billing address.
 */

// Map checkout fields to PayerData object properties.
const FIELD_MAP = {
	'#billing_email': [ 'email_address' ],
	'#billing_last_name': [ 'name', 'surname' ],
	'#billing_first_name': [ 'name', 'given_name' ],
	'#billing_country': [ 'address', 'country_code' ],
	'#billing_address_1': [ 'address', 'address_line_1' ],
	'#billing_address_2': [ 'address', 'address_line_2' ],
	'#billing_state': [ 'address', 'admin_area_1' ],
	'#billing_city': [ 'address', 'admin_area_2' ],
	'#billing_postcode': [ 'address', 'postal_code' ],
	'#billing_phone': [ 'phone' ],
};

/**
 * Returns billing details from the checkout form or global JS object.
 *
 * @return {?PayerDetails} Full billing details, or null on failure.
 */
export function payerData() {
	/**
	 * PayPalCommerceGateway.payer can be set from server-side or via JS:
	 * - Server-side: Set by PHP when a WC customer is known.
	 * - Dynamic JS: When a payment method provided billing data.
	 *
	 * @see {setPayerData}
	 */
	const payer = window.PayPalCommerceGateway?.payer;
	if ( ! payer ) {
		return null;
	}

	const getElementValue = ( selector ) =>
		document.querySelector( selector )?.value;

	// Initialize data with existing payer values.
	const data = {
		email_address: payer.email_address,
		phone: payer.phone,
		name: {
			surname: payer.name?.surname,
			given_name: payer.name?.given_name,
		},
		address: {
			country_code: payer.address?.country_code,
			address_line_1: payer.address?.address_line_1,
			address_line_2: payer.address?.address_line_2,
			admin_area_1: payer.address?.admin_area_1,
			admin_area_2: payer.address?.admin_area_2,
			postal_code: payer.address?.postal_code,
		},
	};

	// Update data with DOM values where they exist.
	Object.entries( FIELD_MAP ).forEach( ( [ selector, path ] ) => {
		const value = getElementValue( selector );
		if ( value ) {
			let current = data;
			path.slice( 0, -1 ).forEach( ( key ) => {
				current = current[ key ] = current[ key ] || {};
			} );
			current[ path[ path.length - 1 ] ] = value;
		}
	} );

	// Handle phone separately due to its nested structure.
	const phoneNumber = data.phone;
	if ( phoneNumber && typeof phoneNumber === 'string' ) {
		data.phone = {
			phone_type: 'HOME',
			phone_number: { national_number: phoneNumber },
		};
	}

	return data;
}

/**
 * Updates the DOM with specific payer details.
 *
 * Used by payment method callbacks that provide dedicated billing details, like Google Pay.
 * Note: This code only works on classic checkout
 *
 * @param {PayerDetails} newData                   - New payer details.
 * @param {boolean}      [overwriteExisting=false] - If set to true, all provided values will
 *                                                 replace existing details. If false, or omitted,
 *                                                 only undefined fields are updated.
 */
export function setPayerData( newData, overwriteExisting = false ) {
	const setValue = ( path, field, value ) => {
		if ( null === value || undefined === value || ! field ) {
			return;
		}

		if ( path[ 0 ] === 'phone' && typeof value === 'object' ) {
			value = value.phone_number?.national_number;
		}

		if ( overwriteExisting || ! field.value ) {
			field.value = value;
		}
	};

	Object.entries( FIELD_MAP ).forEach( ( [ selector, path ] ) => {
		const value = path.reduce( ( obj, key ) => obj?.[ key ], newData );
		const element = document.querySelector( selector );

		setValue( path, element, value );
	} );

	/*
	 * Persist the payer details to the global JS object, to make it available in other modules
	 * via tha `payerData()` accessor.
	 */
	window.PayPalCommerceGateway.payer =
		window.PayPalCommerceGateway.payer || {};
	const currentPayerData = payerData();

	if ( currentPayerData ) {
		Object.entries( newData ).forEach( ( [ key, value ] ) => {
			if (
				overwriteExisting ||
				null !== currentPayerData[ key ] ||
				undefined !== currentPayerData[ key ]
			) {
				currentPayerData[ key ] = value;
			}
		} );

		window.PayPalCommerceGateway.payer = currentPayerData;
	} else {
		window.PayPalCommerceGateway.payer = newData;
	}
}
