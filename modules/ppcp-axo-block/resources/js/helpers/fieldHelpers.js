import { dispatch } from '@wordpress/data';
import { log } from '../../../../ppcp-axo/resources/js/Helper/Debug';

/**
 * Saves the current shipping and billing address to localStorage.
 *
 * @param {Object} shippingAddress - The current shipping address.
 * @param {Object} billingAddress  - The current billing address.
 */
export const snapshotFields = ( shippingAddress, billingAddress ) => {
	if ( ! shippingAddress || ! billingAddress ) {
		log(
			`Shipping or billing address is missing: ${ JSON.stringify( {
				shippingAddress,
				billingAddress,
			} ) }`,
			'warn'
		);
	}

	const originalData = { shippingAddress, billingAddress };
	log( `Snapshot data: ${ JSON.stringify( originalData ) }` );
	try {
		// Save the original data to localStorage
		localStorage.setItem(
			'axoOriginalCheckoutFields',
			JSON.stringify( originalData )
		);
	} catch ( error ) {
		log( `Error saving to localStorage: ${ error }`, 'error' );
	}
};

/**
 * Restores the original shipping and billing addresses from localStorage.
 *
 * @param {Function} updateShippingAddress - Function to update the shipping address.
 * @param {Function} updateBillingAddress  - Function to update the billing address.
 */
export const restoreOriginalFields = (
	updateShippingAddress,
	updateBillingAddress
) => {
	log( 'Attempting to restore original fields' );
	let savedData;
	try {
		// Retrieve saved data from localStorage
		savedData = localStorage.getItem( 'axoOriginalCheckoutFields' );
		log(
			`Data retrieved from localStorage: ${ JSON.stringify( savedData ) }`
		);
	} catch ( error ) {
		log( `Error retrieving from localStorage: ${ error }`, 'error' );
	}

	if ( savedData ) {
		try {
			const parsedData = JSON.parse( savedData );
			// Restore shipping address if available
			if ( parsedData.shippingAddress ) {
				updateShippingAddress( parsedData.shippingAddress );
			} else {
				log( `No shipping address found in saved data`, 'warn' );
			}
			// Restore billing address if available
			if ( parsedData.billingAddress ) {
				log(
					`Restoring billing address:
					${ JSON.stringify( parsedData.billingAddress ) }`
				);
				updateBillingAddress( parsedData.billingAddress );
			} else {
				log( 'No billing address found in saved data', 'warn' );
			}
		} catch ( error ) {
			log( `Error parsing saved data: ${ error }` );
		}
	} else {
		log(
			'No data found in localStorage under axoOriginalCheckoutFields',
			'warn'
		);
	}
};

/**
 * Populates WooCommerce fields with profile data from AXO.
 *
 * @param {Object}   profileData           - The profile data from AXO.
 * @param {Function} setWooShippingAddress - Function to set WooCommerce shipping address.
 * @param {Function} setWooBillingAddress  - Function to set WooCommerce billing address.
 */
export const populateWooFields = (
	profileData,
	setWooShippingAddress,
	setWooBillingAddress
) => {
	const CHECKOUT_STORE_KEY = 'wc/store/checkout';

	log(
		`Populating WooCommerce fields with profile data: ${ JSON.stringify(
			profileData
		) }`
	);

	const checkoutDispatch = dispatch( CHECKOUT_STORE_KEY );

	// Uncheck the 'Use same address for billing' checkbox if the method exists
	if (
		typeof checkoutDispatch.__internalSetUseShippingAsBilling === 'function'
	) {
		checkoutDispatch.__internalSetUseShippingAsBilling( false );
	}

	// Prepare and set shipping address
	const { address, name, phoneNumber } = profileData.shippingAddress;

	const shippingAddress = {
		first_name: name.firstName,
		last_name: name.lastName,
		address_1: address.addressLine1,
		address_2: address.addressLine2 || '',
		city: address.adminArea2,
		state: address.adminArea1,
		postcode: address.postalCode,
		country: address.countryCode,
		phone: phoneNumber.nationalNumber,
	};

	log(
		`Setting WooCommerce shipping address: ${ JSON.stringify(
			shippingAddress
		) }`
	);
	setWooShippingAddress( shippingAddress );

	// Prepare and set billing address
	const billingData = profileData.card.paymentSource.card.billingAddress;

	const billingAddress = {
		first_name: profileData.name.firstName,
		last_name: profileData.name.lastName,
		address_1: billingData.addressLine1,
		address_2: billingData.addressLine2 || '',
		city: billingData.adminArea2,
		state: billingData.adminArea1,
		postcode: billingData.postalCode,
		country: billingData.countryCode,
	};

	log(
		`Setting WooCommerce billing address: ${ JSON.stringify(
			billingAddress
		) }`
	);
	setWooBillingAddress( billingAddress );

	// Collapse shipping address input fields into the card view
	if ( typeof checkoutDispatch.setEditingShippingAddress === 'function' ) {
		checkoutDispatch.setEditingShippingAddress( false );
	}

	// Collapse billing address input fields into the card view
	if ( typeof checkoutDispatch.setEditingBillingAddress === 'function' ) {
		checkoutDispatch.setEditingBillingAddress( false );
	}
};
