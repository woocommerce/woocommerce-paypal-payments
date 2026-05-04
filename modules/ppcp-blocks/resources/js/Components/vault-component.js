import { useEffect, useRef, useState } from '@wordpress/element';
import { loadPayPalScript } from '@ppcp-button/Helper/PayPalScriptLoading';

const namespace = 'ppcpBlocksPaypalExpressButtons';

export const VaultComponent = ( { config, onApproveOrder, onRenderError } ) => {
	const containerRef = useRef( null );
	const vaultInstanceRef = useRef( null );
	const [ sdkReady, setSdkReady ] = useState( false );
	const [ renderFailed, setRenderFailed ] = useState( false );

	const vaultData = config.scriptData.vault_component;

	// Load SDK if not already loaded.
	useEffect( () => {
		const paypal = window[ namespace ];
		if ( paypal?.SavedPaymentMethods ) {
			setSdkReady( true );
			return;
		}

		loadPayPalScript( namespace, config.scriptData )
			.then( () => setSdkReady( true ) )
			.catch( ( error ) => {
				console.error( 'Failed to load PayPal SDK for Vault:', error );
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

		const paypal = window[ namespace ];
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
