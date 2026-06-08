import { loadScript } from '@paypal/paypal-js';

const VAULT_NAMESPACE = 'ppcpVaultComponent';
const CONTAINER_SELECTOR = '#ppcp-vault-component';

// Module-level cache so repeated render calls don't trigger duplicate loads.
let vaultSdkPromise = null;

class VaultRenderer {
	constructor( config ) {
		this.config = config;
		this.vaultInstance = null;
		this.rendered = false;
		this._renderGen = 0;
	}

	async loadSdk() {
		if ( window[ VAULT_NAMESPACE ]?.SavedPaymentMethods ) {
			return;
		}

		if ( ! vaultSdkPromise ) {
			const vaultData = this.config.vault_component;
			const options = {
				clientId: this.config.url_params?.[ 'client-id' ],
				components: 'saved-payment-methods,buttons,messages',
				commit: 'false',
				'data-namespace': VAULT_NAMESPACE,
				'data-sdk-client-token': vaultData.sdk_client_token,
			};

			const sdkBaseUrl = this.config.script_attributes?.sdkBaseUrl;
			if ( sdkBaseUrl ) {
				options.sdkBaseUrl = sdkBaseUrl;
			}

			vaultSdkPromise = loadScript( options );
		}

		await vaultSdkPromise;
	}

	async render( onApproveCallback, onCancelCallback ) {
		const container = document.querySelector( CONTAINER_SELECTOR );
		if ( ! container || this.rendered ) {
			return;
		}

		// Claim this generation so concurrent calls and stale post-reset calls abort.
		const gen = ++this._renderGen;

		await this.loadSdk();

		if ( this._renderGen !== gen || this.rendered ) {
			return;
		}

		const paypal = window[ VAULT_NAMESPACE ];
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
		this._renderGen++;
	}

	reset() {
		this.vaultInstance?.close?.();
		this.vaultInstance = null;
		this.rendered = false;
		this._renderGen++;
		const container = document.querySelector( CONTAINER_SELECTOR );
		if ( container ) {
			container.innerHTML = '';
		}
	}

	isRendered() {
		return this.rendered;
	}
}

export default VaultRenderer;
