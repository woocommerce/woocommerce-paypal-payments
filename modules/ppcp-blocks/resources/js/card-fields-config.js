export async function createOrder() {
	const config = wc.wcSettings.getSetting( 'ppcp-credit-card-gateway_data' );

	return fetch( config.scriptData.ajax.create_order.endpoint, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify( {
			nonce: config.scriptData.ajax.create_order.nonce,
			context: config.scriptData.context,
			payment_method: 'ppcp-credit-card-gateway',
			save_payment_method:
				localStorage.getItem( 'ppcp-save-card-payment' ) === 'true',
		} ),
	} )
		.then( ( response ) => response.json() )
		.then( ( order ) => {
			return order.data.id;
		} )
		.catch( ( err ) => {
			console.error( err );
		} );
}

export async function onApprove( data ) {
	const config = wc.wcSettings.getSetting( 'ppcp-credit-card-gateway_data' );

	return fetch( config.scriptData.ajax.approve_order.endpoint, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify( {
			order_id: data.orderID,
			nonce: config.scriptData.ajax.approve_order.nonce,
		} ),
	} )
		.then( ( response ) => response.json() )
		.then( ( data ) => {
			localStorage.removeItem( 'ppcp-save-card-payment' );
		} )
		.catch( ( err ) => {
			console.error( err );
		} );
}
