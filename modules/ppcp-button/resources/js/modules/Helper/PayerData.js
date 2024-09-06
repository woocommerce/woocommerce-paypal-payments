/**
 * Name details.
 *
 * @typedef {Object} NameDetails
 * @property {string} [given_name] - First name, e.g. "John".
 * @property {string} [surname]    - Last name, e.g. "Doe".
 */

/**
 * Postal address details.
 *
 * @typedef {Object} AddressDetails
 * @property {string} [country_code]   - Country code (2-letter).
 * @property {string} [address_line_1] - Address details, line 1 (street, house number).
 * @property {string} [address_line_2] - Address details, line 2.
 * @property {string} [admin_area_1]   - State or region.
 * @property {string} [admin_area_2]   - State or region.
 * @property {string} [postal_code]    - Zip code.
 */

/**
 * Phone details.
 *
 * @typedef {Object} PhoneDetails
 * @property {string}                    [phone_type]   - Type, usually 'HOME'
 * @property {{national_number: string}} [phone_number] - Phone number details.
 */

/**
 * Payer details.
 *
 * @typedef {Object} PayerDetails
 * @property {string}         [email_address] - Email address for billing communication.
 * @property {PhoneDetails}   [phone]         - Phone number for billing communication.
 * @property {NameDetails}    [name]          - Payer's name.
 * @property {AddressDetails} [address]       - Postal billing address.
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

function normalizePayerDetails( details ) {
	return {
		email_address: details.email_address,
		phone: details.phone,
		name: {
			surname: details.name?.surname,
			given_name: details.name?.given_name,
		},
		address: {
			country_code: details.address?.country_code,
			address_line_1: details.address?.address_line_1,
			address_line_2: details.address?.address_line_2,
			admin_area_1: details.address?.admin_area_1,
			admin_area_2: details.address?.admin_area_2,
			postal_code: details.address?.postal_code,
		},
	};
}

function mergePayerDetails( firstPayer, secondPayer ) {
	const mergeNestedObjects = ( target, source ) => {
		for ( const [ key, value ] of Object.entries( source ) ) {
			if ( null !== value && undefined !== value ) {
				if ( 'object' === typeof value ) {
					target[ key ] = mergeNestedObjects(
						target[ key ] || {},
						value
					);
				} else {
					target[ key ] = value;
				}
			}
		}
		return target;
	};

	return mergeNestedObjects(
		normalizePayerDetails( firstPayer ),
		normalizePayerDetails( secondPayer )
	);
}

function getCheckoutBillingDetails() {
	const getElementValue = ( selector ) =>
		document.querySelector( selector )?.value;

	const setNestedValue = ( obj, path, value ) => {
		let current = obj;
		for ( let i = 0; i < path.length - 1; i++ ) {
			current = current[ path[ i ] ] = current[ path[ i ] ] || {};
		}
		current[ path[ path.length - 1 ] ] = value;
	};

	const data = {};

	Object.entries( FIELD_MAP ).forEach( ( [ selector, path ] ) => {
		const value = getElementValue( selector );
		if ( value ) {
			setNestedValue( data, path, value );
		}
	} );

	if ( data.phone && 'string' === typeof data.phone ) {
		data.phone = {
			phone_type: 'HOME',
			phone_number: { national_number: data.phone },
		};
	}

	return data;
}

function setCheckoutBillingDetails( payer ) {
	const setValue = ( path, field, value ) => {
		if ( null === value || undefined === value || ! field ) {
			return;
		}

		if ( 'phone' === path[ 0 ] && 'object' === typeof value ) {
			value = value.phone_number?.national_number;
		}

		field.value = value;
	};

	const getNestedValue = ( obj, path ) =>
		path.reduce( ( current, key ) => current?.[ key ], obj );

	Object.entries( FIELD_MAP ).forEach( ( [ selector, path ] ) => {
		const value = getNestedValue( payer, path );
		const element = document.querySelector( selector );

		setValue( path, element, value );
	} );
}

export function getWooCommerceCustomerDetails() {
	// Populated on server-side with details about the current WooCommerce customer.
	return window?.PayPalCommerceGateway?.payer;
}

export function getSessionBillingDetails() {
	// Populated by JS via `setSessionBillingDetails()`
	return window._PpcpPayerSessionDetails;
}

/**
 * Stores customer details in the current JS context for use in the same request.
 * Details that are set are not persisted during navigation.
 *
 * @param {unknown} details - New payer details
 */
export function setSessionBillingDetails( details ) {
	if ( ! details || 'object' !== typeof details ) {
		return;
	}

	window._PpcpPayerSessionDetails = normalizePayerDetails( details );
}

export function payerData() {
	const payer = getWooCommerceCustomerDetails() ?? getSessionBillingDetails();

	if ( ! payer ) {
		return null;
	}

	const formData = getCheckoutBillingDetails();

	if ( formData ) {
		return mergePayerDetails( payer, formData );
	}

	return normalizePayerDetails( payer );
}

export function setPayerData( payerDetails, updateCheckoutForm = false ) {
	setSessionBillingDetails( payerDetails );

	if ( updateCheckoutForm ) {
		setCheckoutBillingDetails( payerDetails );
	}
}
