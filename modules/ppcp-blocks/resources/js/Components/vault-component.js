import { useEffect, useRef, useState } from '@wordpress/element';
import { loadScript } from '@paypal/paypal-js';

const VAULT_NAMESPACE = 'ppcpVaultComponent';

// Module-level cache so re-renders don't trigger duplicate loads.
let vaultSdkPromise = null;

export const VaultComponent = ( { config, onApproveOrder, onRenderError } ) => {
	const containerRef = useRef( null );
	const vaultInstanceRef = useRef( null );
	const [ sdkReady, setSdkReady ] = useState( false );
	const [ renderFailed, setRenderFailed ] = useState( false );

	const vaultData = config.scriptData.vault_component;

	// Load SDK into a dedicated namespace so it does not interfere with the
	// main ppcpBlocksPaypalExpressButtons SDK or widgetBuilder state.
	useEffect( () => {
		if ( window[ VAULT_NAMESPACE ]?.SavedPaymentMethods ) {
			setSdkReady( true );
			return;
		}

		if ( ! vaultSdkPromise ) {
			const options = {
				clientId: config.scriptData.client_id,
				components: 'saved-payment-methods,buttons,messages',
				'data-namespace': VAULT_NAMESPACE,
				'data-sdk-client-token': vaultData.sdk_client_token,
			};
			const sdkBaseUrl = config.scriptData.script_attributes?.sdkBaseUrl;
			if ( sdkBaseUrl ) {
				options.sdkBaseUrl = sdkBaseUrl;
			}
			vaultSdkPromise = loadScript( options );
		}

		vaultSdkPromise
			.then( () => setSdkReady( true ) )
			.catch( ( error ) => {
				console.error( 'Failed to load PayPal SDK for Vault:', error );
				vaultSdkPromise = null;
				setRenderFailed( true );
				onRenderError?.();
			} );
	}, [] );

	// Render paypal.SavedPaymentMethods() once SDK is ready.
	useEffect( () => {
		if (
			! sdkReady ||
			! containerRef.current ||
			vaultInstanceRef.current
		) {
			return;
		}

		const paypal = window[ VAULT_NAMESPACE ];
		if ( ! paypal?.SavedPaymentMethods ) {
			console.error(
				'PayPal SavedPaymentMethods SDK component not available.'
			);
			setRenderFailed( true );
			onRenderError?.();
			return;
		}

		try {
			vaultInstanceRef.current = paypal.SavedPaymentMethods( {
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
					onApproveOrder?.( data.orderID );
				},
				onCancel: () => {
					// No changes, component remains unchanged.
				},
				onError: ( error ) => {
					console.error( 'Vault Component error:', error );
				},
			} );

			vaultInstanceRef.current
				.render( containerRef.current )
				.catch( ( error ) => {
					console.error( 'Vault Component render failed:', error );
					setRenderFailed( true );
					onRenderError?.();
				} );
		} catch ( error ) {
			console.error( 'Vault Component init failed:', error );
			setRenderFailed( true );
			onRenderError?.();
		}

		return () => {
			vaultInstanceRef.current?.close?.();
			vaultInstanceRef.current = null;
		};
	}, [ sdkReady ] );

	if ( renderFailed ) {
		return null;
	}

	return (
		<div
			id="ppcp-vault-component-container"
			ref={ containerRef }
			style={ { minHeight: '48px' } }
		/>
	);
};
