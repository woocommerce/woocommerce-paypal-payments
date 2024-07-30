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

export async function createVaultSetupToken() {
	const config = wc.wcSettings.getSetting( 'ppcp-credit-card-gateway_data' );

	return fetch( config.scriptData.ajax.create_setup_token.endpoint, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify( {
			nonce: config.scriptData.ajax.create_setup_token.nonce,
			payment_method: 'ppcp-credit-card-gateway',
		} ),
	} )
		.then( ( response ) => response.json() )
		.then( ( result ) => {
			console.log( result );
			return result.data.id;
		} )
		.catch( ( err ) => {
			console.error( err );
		} );
}

export async function onApproveSavePayment( { vaultSetupToken } ) {
	const config = wc.wcSettings.getSetting( 'ppcp-credit-card-gateway_data' );

	let endpoint =
		config.scriptData.ajax.create_payment_token_for_guest.endpoint;
	let bodyContent = {
		nonce: config.scriptData.ajax.create_payment_token_for_guest.nonce,
		vault_setup_token: vaultSetupToken,
	};

	if ( config.scriptData.user.is_logged_in ) {
		endpoint = config.scriptData.ajax.create_payment_token.endpoint;

		bodyContent = {
			nonce: config.scriptData.ajax.create_payment_token.nonce,
			vault_setup_token: vaultSetupToken,
			is_free_trial_cart: config.scriptData.is_free_trial_cart,
		};
	}

	const response = await fetch( endpoint, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify( bodyContent ),
	} );

	const result = await response.json();
	if ( result.success !== true ) {
		console.error( result );
	}
}
