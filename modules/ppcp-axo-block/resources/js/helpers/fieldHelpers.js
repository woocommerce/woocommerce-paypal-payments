export const snapshotFields = ( shippingAddress, billingAddress ) => {
	if ( ! shippingAddress || ! billingAddress ) {
		console.warn( 'Shipping or billing address is missing:', {
			shippingAddress,
			billingAddress,
		} );
	}

	const originalData = { shippingAddress, billingAddress };
	console.log( 'Snapshot data:', originalData );
	localStorage.setItem(
		'axoOriginalCheckoutFields',
		JSON.stringify( originalData )
	);
	console.log( 'Original fields saved to localStorage', originalData );
};

export const restoreOriginalFields = (
	updateShippingAddress,
	updateBillingAddress
) => {
	const savedData = localStorage.getItem( 'axoOriginalCheckoutFields' );
	console.log( 'Data retrieved from localStorage:', savedData );

	if ( savedData ) {
		const parsedData = JSON.parse( savedData );
		if ( parsedData.shippingAddress ) {
			updateShippingAddress( parsedData.shippingAddress );
		}
		if ( parsedData.billingAddress ) {
			updateBillingAddress( parsedData.billingAddress );
		}
		console.log( 'Original fields restored from localStorage', parsedData );
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
	// Save shipping address
	const { address, name, phoneNumber } = profileData.shippingAddress;

	setWooShippingAddress( {
		first_name: name.firstName,
		last_name: name.lastName,
		address_1: address.addressLine1,
		address_2: address.addressLine2 || '',
		city: address.adminArea2,
		state: address.adminArea1,
		postcode: address.postalCode,
		country: address.countryCode,
		phone: phoneNumber.nationalNumber,
	} );

	// Save billing address
	const billingData = profileData.card.paymentSource.card.billingAddress;

	setWooBillingAddress( {
		first_name: profileData.name.firstName,
		last_name: profileData.name.lastName,
		address_1: billingData.addressLine1,
		address_2: billingData.addressLine2 || '',
		city: billingData.adminArea2,
		state: billingData.adminArea1,
		postcode: billingData.postalCode,
		country: billingData.countryCode,
	} );
};
