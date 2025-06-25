import {
	paypalOrderToWcAddresses,
	paypalSubscriptionToWcAddresses,
} from './Helper/Address';

export const createOrder = async ( data, config, onError, onClose ) => {
	try {
		const requestBody = {
			nonce: config.scriptData.ajax.create_order.nonce,
			bn_code: '',
			context: config.scriptData.context,
			payment_method: 'ppcp-gateway',
			funding_source: window.ppcpFundingSource ?? 'paypal',
			createaccount: false,
			...( data?.paymentSource && {
				payment_source: data.paymentSource,
			} ),
		};

		const res = await fetch( config.scriptData.ajax.create_order.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			body: JSON.stringify( requestBody ),
		} );

		const json = await res.json();

		if ( ! json.success ) {
			if ( json.data?.details?.length > 0 ) {
				throw new Error(
					json.data.details
						.map( ( d ) => `${ d.issue } ${ d.description }` )
						.join( '<br/>' )
				);
			} else if ( json.data?.message ) {
				throw new Error( json.data.message );
			}

			throw new Error( config.scriptData.labels.error.generic );
		}

		return json.data.id;
	} catch ( err ) {
		console.error( err );

		onError( err.message );

		onClose();

		throw err;
	}
};

export const handleApprove = async (
	data,
	actions,
	config,
	shouldHandleShippingInPayPal,
	shippingData,
	setPaypalOrder,
	shouldskipFinalConfirmation,
	getCheckoutRedirectUrl,
	setGotoContinuationOnError,
	onSubmit,
	onError,
	onClose
) => {
	try {
		const order = await actions.order.get();

		if ( order ) {
			const addresses = paypalOrderToWcAddresses( order );

			const promises = [
				// save address on server
				wp.data.dispatch( 'wc/store/cart' ).updateCustomerData( {
					billing_address: addresses.billingAddress,
					shipping_address: addresses.shippingAddress,
				} ),
			];
			if ( shouldHandleShippingInPayPal() ) {
				// set address in UI
				promises.push(
					wp.data
						.dispatch( 'wc/store/cart' )
						.setBillingAddress( addresses.billingAddress )
				);
				if ( shippingData.needsShipping ) {
					promises.push(
						wp.data
							.dispatch( 'wc/store/cart' )
							.setShippingAddress( addresses.shippingAddress )
					);
				}
			}
			await Promise.all( promises );
		}

		setPaypalOrder( order );

		const res = await fetch(
			config.scriptData.ajax.approve_order.endpoint,
			{
				method: 'POST',
				credentials: 'same-origin',
				body: JSON.stringify( {
					nonce: config.scriptData.ajax.approve_order.nonce,
					order_id: data.orderID,
					funding_source: window.ppcpFundingSource ?? 'paypal',
				} ),
			}
		);

		const json = await res.json();

		if ( ! json.success ) {
			if (
				typeof actions !== 'undefined' &&
				typeof actions.restart !== 'undefined'
			) {
				return actions.restart();
			}
			if ( json.data?.message ) {
				throw new Error( json.data.message );
			}

			throw new Error( config.scriptData.labels.error.generic );
		}

		if ( ! shouldskipFinalConfirmation() ) {
			location.href = getCheckoutRedirectUrl();
		} else {
			setGotoContinuationOnError( true );
			onSubmit();
		}
	} catch ( err ) {
		console.error( err );

		onError( err.message );

		onClose();

		throw err;
	}
};

export const createSubscription = async ( data, actions, config ) => {
	let planId = config.scriptData.subscription_plan_id;
	if (
		config.scriptData.variable_paypal_subscription_variation_from_cart !==
		''
	) {
		planId =
			config.scriptData.variable_paypal_subscription_variation_from_cart;
	}

	return actions.subscription.create( {
		plan_id: planId,
	} );
};

export const handleApproveSubscription = async (
	data,
	actions,
	config,
	shouldHandleShippingInPayPal,
	shippingData,
	setPaypalOrder,
	shouldskipFinalConfirmation,
	getCheckoutRedirectUrl,
	setGotoContinuationOnError,
	onSubmit,
	onError,
	onClose
) => {
	try {
		const subscription = await actions.subscription.get();

		if ( subscription ) {
			const addresses = paypalSubscriptionToWcAddresses( subscription );

			const promises = [
				// save address on server
				wp.data.dispatch( 'wc/store/cart' ).updateCustomerData( {
					billing_address: addresses.billingAddress,
					shipping_address: addresses.shippingAddress,
				} ),
			];
			if ( shouldHandleShippingInPayPal() ) {
				// set address in UI
				promises.push(
					wp.data
						.dispatch( 'wc/store/cart' )
						.setBillingAddress( addresses.billingAddress )
				);
				if ( shippingData.needsShipping ) {
					promises.push(
						wp.data
							.dispatch( 'wc/store/cart' )
							.setShippingAddress( addresses.shippingAddress )
					);
				}
			}
			await Promise.all( promises );
		}

		setPaypalOrder( subscription );

		const res = await fetch(
			config.scriptData.ajax.approve_subscription.endpoint,
			{
				method: 'POST',
				credentials: 'same-origin',
				body: JSON.stringify( {
					nonce: config.scriptData.ajax.approve_subscription.nonce,
					order_id: data.orderID,
					subscription_id: data.subscriptionID,
				} ),
			}
		);

		const json = await res.json();

		if ( ! json.success ) {
			if (
				typeof actions !== 'undefined' &&
				typeof actions.restart !== 'undefined'
			) {
				return actions.restart();
			}
			if ( json.data?.message ) {
				throw new Error( json.data.message );
			}

			throw new Error( config.scriptData.labels.error.generic );
		}

		if ( ! shouldskipFinalConfirmation() ) {
			location.href = getCheckoutRedirectUrl();
		} else {
			setGotoContinuationOnError( true );
			onSubmit();
		}
	} catch ( err ) {
		console.error( err );

		onError( err.message );

		onClose();

		throw err;
	}
};

export const createVaultSetupToken = async ( config ) => {
	return fetch( config.scriptData.ajax.create_setup_token.endpoint, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify( {
			nonce: config.scriptData.ajax.create_setup_token.nonce,
			payment_method: 'ppcp-gateway',
		} ),
	} )
		.then( ( response ) => response.json() )
		.then( ( result ) => {
			return result.data.id;
		} )
		.catch( ( err ) => {
			console.error( err );
		} );
};

export const onApproveSavePayment = async (
	vaultSetupToken,
	config,
	onSubmit
) => {
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
	if ( result.success === true ) {
		onSubmit();
	}

	console.error( result );
};
