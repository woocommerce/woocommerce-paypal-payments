import {
	getCurrentPaymentMethod,
	PaymentMethods,
} from '../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState';

export function buttonConfiguration( ppcp_add_payment_method, errorHandler ) {
	return {
		createVaultSetupToken: async () => {
			const response = await fetch(
				ppcp_add_payment_method.ajax.create_setup_token.endpoint,
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( {
						nonce: ppcp_add_payment_method.ajax.create_setup_token
							.nonce,
					} ),
				}
			);

			const result = await response.json();
			if ( result.data.id ) {
				return result.data.id;
			}

			errorHandler.message( ppcp_add_payment_method.error_message );
		},
		onApprove: async ( { vaultSetupToken } ) => {
			const response = await fetch(
				ppcp_add_payment_method.ajax.create_payment_token.endpoint,
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( {
						nonce: ppcp_add_payment_method.ajax.create_payment_token
							.nonce,
						vault_setup_token: vaultSetupToken,
					} ),
				}
			);

			const result = await response.json();
			if ( result.success === true ) {
				window.location.href =
					ppcp_add_payment_method.payment_methods_page;
				return;
			}

			errorHandler.message( ppcp_add_payment_method.error_message );
		},
		onError: ( error ) => {
			console.error( error );
			errorHandler.message( ppcp_add_payment_method.error_message );
		},
	};
}

export function cardFieldsConfiguration(
	ppcp_add_payment_method,
	errorHandler
) {
	return {
		createVaultSetupToken: async () => {
			const response = await fetch(
				ppcp_add_payment_method.ajax.create_setup_token.endpoint,
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( {
						nonce: ppcp_add_payment_method.ajax.create_setup_token
							.nonce,
						payment_method: PaymentMethods.CARDS,
						verification_method:
							ppcp_add_payment_method.verification_method,
					} ),
				}
			);

			const result = await response.json();
			if ( result.data.id ) {
				return result.data.id;
			}

			errorHandler.message( ppcp_add_payment_method.error_message );
		},
		onApprove: async ( { vaultSetupToken } ) => {
			const isFreeTrialCart =
				ppcp_add_payment_method?.is_free_trial_cart ?? false;
			const response = await fetch(
				ppcp_add_payment_method.ajax.create_payment_token.endpoint,
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( {
						nonce: ppcp_add_payment_method.ajax.create_payment_token
							.nonce,
						vault_setup_token: vaultSetupToken,
						payment_method: PaymentMethods.CARDS,
						is_free_trial_cart: isFreeTrialCart,
					} ),
				}
			);

			const result = await response.json();
			if ( result.success === true ) {
				const context = ppcp_add_payment_method?.context ?? '';
				if ( context === 'checkout' ) {
					document.querySelector( '#place_order' ).click();
					return;
				}

				if (
					ppcp_add_payment_method.is_subscription_change_payment_page
				) {
					const subscriptionId =
						ppcp_add_payment_method.subscription_id_to_change_payment;
					if ( subscriptionId && result.data ) {
						const req = await fetch(
							ppcp_add_payment_method.ajax
								.subscription_change_payment_method.endpoint,
							{
								method: 'POST',
								credentials: 'same-origin',
								headers: {
									'Content-Type': 'application/json',
								},
								body: JSON.stringify( {
									nonce: ppcp_add_payment_method.ajax
										.subscription_change_payment_method
										.nonce,
									subscription_id: subscriptionId,
									payment_method: getCurrentPaymentMethod(),
									wc_payment_token_id: result.data,
								} ),
							}
						);

						const res = await req.json();
						if ( res.success === true ) {
							window.location.href = `${ ppcp_add_payment_method.view_subscriptions_page }/${ subscriptionId }`;
							return;
						}
					}

					return;
				}

				window.location.href =
					ppcp_add_payment_method.payment_methods_page;
				return;
			}

			this.errorHandler.message( ppcp_add_payment_method.error_message );
		},
		onError: ( error ) => {
			console.error( error );
			errorHandler.message( ppcp_add_payment_method.error_message );
		},
	};
}

export function addPaymentMethodConfiguration( ppcp_add_payment_method ) {
	return {
		createVaultSetupToken: async () => {
			const response = await fetch(
				ppcp_add_payment_method.ajax.create_setup_token.endpoint,
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( {
						nonce: ppcp_add_payment_method.ajax.create_setup_token
							.nonce,
						payment_method: getCurrentPaymentMethod(),
					} ),
				}
			);

			const result = await response.json();
			if ( result.data.id ) {
				return result.data.id;
			}

			console.error( result );
		},
		onApprove: async ( { vaultSetupToken } ) => {
			const response = await fetch(
				ppcp_add_payment_method.ajax.create_payment_token_for_guest
					.endpoint,
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( {
						nonce: ppcp_add_payment_method.ajax
							.create_payment_token_for_guest.nonce,
						vault_setup_token: vaultSetupToken,
					} ),
				}
			);

			const result = await response.json();
			if ( result.success === true ) {
				document.querySelector( '#place_order' ).click();
				return;
			}

			console.error( result );
		},
		onError: ( error ) => {
			console.error( error );
		},
	};
}
