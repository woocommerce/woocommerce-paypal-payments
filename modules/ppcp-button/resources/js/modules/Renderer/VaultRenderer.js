const CONTAINER_SELECTOR = '#ppcp-vault-component';

class VaultRenderer {
	constructor( config ) {
		this.config = config;
		this.vaultInstance = null;
		this.rendered = false;
	}

	render( onApproveCallback, onCancelCallback ) {
		const container = document.querySelector( CONTAINER_SELECTOR );
		if ( ! container || this.rendered ) {
			return;
		}

		const paypal = window.paypal;
		if ( ! paypal?.SavedPaymentMethods ) {
			console.error(
				'PayPal SavedPaymentMethods SDK component not available.'
			);
			return;
		}

		const vaultData = this.config.vault_component;

		try {
			this.vaultInstance = paypal.SavedPaymentMethods( {
				createOrder: async () => {
					const res = await fetch(
						vaultData.ajax.create_order.endpoint,
						{
							method: 'POST',
							credentials: 'same-origin',
							headers: {
								'Content-Type': 'application/json',
							},
							body: JSON.stringify( {
								nonce: vaultData.ajax.create_order.nonce,
								vault_token_id: vaultData.token_id,
							} ),
						}
					);

					const json = await res.json();

					if ( ! json.success ) {
						throw new Error(
							json.data?.message || 'Order creation failed.'
						);
					}

					return json.data.id;
				},
				onApprove: async ( data ) => {
					onApproveCallback?.( data.orderID );
				},
				onCancel: () => {
					onCancelCallback?.();
				},
				onError: ( error ) => {
					console.error( 'Vault Component error:', error );
				},
			} );

			this.vaultInstance.render( container ).catch( ( error ) => {
				console.error( 'Vault Component render failed:', error );
				this.rendered = false;
			} );

			this.rendered = true;
		} catch ( error ) {
			console.error( 'Vault Component init failed:', error );
		}
	}

	close() {
		this.vaultInstance?.close?.();
		this.vaultInstance = null;
		this.rendered = false;
	}

	reset() {
		this.vaultInstance = null;
		this.rendered = false;
	}

	isRendered() {
		return this.rendered;
	}
}

export default VaultRenderer;
