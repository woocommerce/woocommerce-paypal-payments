export const snapshotFields = ( shippingAddress, billingAddress ) => {
	if ( ! shippingAddress || ! billingAddress ) {
		console.warn( 'Shipping or billing address is missing:', {
			shippingAddress,
			billingAddress,
		} );
	}

	const originalData = { shippingAddress, billingAddress };
	console.log( 'Snapshot data:', originalData ); // Debug data
	localStorage.setItem(
		'originalCheckoutFields',
		JSON.stringify( originalData )
	);
	console.log( 'Original fields saved to localStorage', originalData );
};

export const restoreOriginalFields = (
	updateShippingAddress,
	updateBillingAddress
) => {
	const savedData = localStorage.getItem( 'originalCheckoutFields' );
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
			'No data found in localStorage under originalCheckoutFields'
		);
	}
};
