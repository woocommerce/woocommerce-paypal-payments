import { dispatch } from '@wordpress/data';

export const snapshotFields = ( shippingAddress, billingAddress ) => {
	console.log( 'Attempting to snapshot fields' );
	if ( ! shippingAddress || ! billingAddress ) {
		console.warn( 'Shipping or billing address is missing:', {
			shippingAddress,
			billingAddress,
		} );
	}

	const originalData = { shippingAddress, billingAddress };
	console.log( 'Snapshot data:', originalData );
	try {
		localStorage.setItem(
			'axoOriginalCheckoutFields',
			JSON.stringify( originalData )
		);
		console.log( 'Original fields saved to localStorage', originalData );
	} catch ( error ) {
		console.error( 'Error saving to localStorage:', error );
	}
};

export const restoreOriginalFields = (
	updateShippingAddress,
	updateBillingAddress
) => {
	console.log( 'Attempting to restore original fields' );
	let savedData;
	try {
		savedData = localStorage.getItem( 'axoOriginalCheckoutFields' );
		console.log( 'Data retrieved from localStorage:', savedData );
	} catch ( error ) {
		console.error( 'Error retrieving from localStorage:', error );
	}

	if ( savedData ) {
		try {
			const parsedData = JSON.parse( savedData );
			console.log( 'Parsed data:', parsedData );
			if ( parsedData.shippingAddress ) {
				console.log(
					'Restoring shipping address:',
					parsedData.shippingAddress
				);
				updateShippingAddress( parsedData.shippingAddress );
			} else {
				console.warn( 'No shipping address found in saved data' );
			}
			if ( parsedData.billingAddress ) {
				console.log(
					'Restoring billing address:',
					parsedData.billingAddress
				);
				updateBillingAddress( parsedData.billingAddress );
			} else {
				console.warn( 'No billing address found in saved data' );
			}
			console.log(
				'Original fields restored from localStorage',
				parsedData
			);
		} catch ( error ) {
			console.error( 'Error parsing saved data:', error );
		}
	} else {
		console.warn(
			'No data found in localStorage under axoOriginalCheckoutFields'
		);
	}
};

export const populateWooFields = (
	profileData,
	setWooShippingAddress,
	setWooBillingAddress
) => {
	const CHECKOUT_STORE_KEY = 'wc/store/checkout';

	console.log(
		'Populating WooCommerce fields with profile data:',
		profileData
	);

	const checkoutDispatch = dispatch( CHECKOUT_STORE_KEY );

	// Uncheck the 'Use same address for billing' checkbox if the method exists.
	if (
		typeof checkoutDispatch.__internalSetUseShippingAsBilling === 'function'
	) {
		checkoutDispatch.__internalSetUseShippingAsBilling( false );
	}

	// Save shipping address.
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

	console.log( 'Setting WooCommerce shipping address:', shippingAddress );
	setWooShippingAddress( shippingAddress );

	// Save billing address.
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

	console.log( 'Setting WooCommerce billing address:', billingAddress );
	setWooBillingAddress( billingAddress );

	// Collapse shipping address input fields into the card view.
	if ( typeof checkoutDispatch.setEditingShippingAddress === 'function' ) {
		checkoutDispatch.setEditingShippingAddress( false );
	}

	// Collapse billing address input fields into the card view.
	if ( typeof checkoutDispatch.setEditingBillingAddress === 'function' ) {
		checkoutDispatch.setEditingBillingAddress( false );
	}
};
