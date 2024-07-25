import {
	getCurrentPaymentMethod,
	PaymentMethods,
} from '../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState';

class Configuration {
	constructor( ppcp_add_payment_method, errorHandler ) {
		this.ppcp_add_payment_method = ppcp_add_payment_method;
		this.errorHandler = errorHandler;
	}

	buttonConfiguration() {
		return {
			createVaultSetupToken: async () => {
				const response = await fetch(
					this.ppcp_add_payment_method.ajax.create_setup_token
						.endpoint,
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							nonce: this.ppcp_add_payment_method.ajax
								.create_setup_token.nonce,
						} ),
					}
				);

				const result = await response.json();
				if ( result.data.id ) {
					return result.data.id;
				}

				this.errorHandler.message(
					this.ppcp_add_payment_method.error_message
				);
			},
			onApprove: async ( { vaultSetupToken } ) => {
				const response = await fetch(
					this.ppcp_add_payment_method.ajax.create_payment_token
						.endpoint,
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							nonce: this.ppcp_add_payment_method.ajax
								.create_payment_token.nonce,
							vault_setup_token: vaultSetupToken,
						} ),
					}
				);

				const result = await response.json();
				if ( result.success === true ) {
					window.location.href =
						this.ppcp_add_payment_method.payment_methods_page;
					return;
				}

				this.errorHandler.message(
					this.ppcp_add_payment_method.error_message
				);
			},
			onError: ( error ) => {
				console.error( error );
				this.errorHandler.message(
					this.ppcp_add_payment_method.error_message
				);
			},
		};
	}

	cardFieldsConfiguration() {
		return {
			createVaultSetupToken: async () => {
				const response = await fetch(
					this.ppcp_add_payment_method.ajax.create_setup_token
						.endpoint,
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							nonce: this.ppcp_add_payment_method.ajax
								.create_setup_token.nonce,
							payment_method: PaymentMethods.CARDS,
							verification_method:
								this.ppcp_add_payment_method
									.verification_method,
						} ),
					}
				);

				const result = await response.json();
				if ( result.data.id ) {
					return result.data.id;
				}

				this.errorHandler.message(
					this.ppcp_add_payment_method.error_message
				);
			},
			onApprove: async ( { vaultSetupToken } ) => {
				const isFreeTrialCart =
					this.ppcp_add_payment_method?.is_free_trial_cart ?? false;
				const response = await fetch(
					this.ppcp_add_payment_method.ajax.create_payment_token
						.endpoint,
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							nonce: this.ppcp_add_payment_method.ajax
								.create_payment_token.nonce,
							vault_setup_token: vaultSetupToken,
							payment_method: PaymentMethods.CARDS,
							is_free_trial_cart: isFreeTrialCart,
						} ),
					}
				);

				const result = await response.json();
				if ( result.success === true ) {
					const context = this.ppcp_add_payment_method?.context ?? '';
					if ( context === 'checkout' ) {
						document.querySelector( '#place_order' ).click();
						return;
					}

					if (
						this.ppcp_add_payment_method
							.is_subscription_change_payment_page
					) {
						const subscriptionId =
							this.ppcp_add_payment_method
								.subscription_id_to_change_payment;
						if ( subscriptionId && result.data ) {
							const req = await fetch(
								this.ppcp_add_payment_method.ajax
									.subscription_change_payment_method
									.endpoint,
								{
									method: 'POST',
									credentials: 'same-origin',
									headers: {
										'Content-Type': 'application/json',
									},
									body: JSON.stringify( {
										nonce: this.ppcp_add_payment_method.ajax
											.subscription_change_payment_method
											.nonce,
										subscription_id: subscriptionId,
										payment_method:
											getCurrentPaymentMethod(),
										wc_payment_token_id: result.data,
									} ),
								}
							);

							const res = await req.json();
							if ( res.success === true ) {
								window.location.href = `${ this.ppcp_add_payment_method.view_subscriptions_page }/${ subscriptionId }`;
								return;
							}
						}

						return;
					}

					window.location.href =
						this.ppcp_add_payment_method.payment_methods_page;
					return;
				}

				this.errorHandler.message(
					this.ppcp_add_payment_method.error_message
				);
			},
			onError: ( error ) => {
				console.error( error );
				this.errorHandler.message(
					this.ppcp_add_payment_method.error_message
				);
			},
		};
	}

	addPaymentMethodConfiguration() {
		return {
			createVaultSetupToken: async () => {
				const response = await fetch(
					this.ppcp_add_payment_method.ajax.create_setup_token
						.endpoint,
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							nonce: this.ppcp_add_payment_method.ajax
								.create_setup_token.nonce,
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
					this.ppcp_add_payment_method.ajax
						.create_payment_token_for_guest.endpoint,
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							nonce: this.ppcp_add_payment_method.ajax
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
}

export default Configuration;
