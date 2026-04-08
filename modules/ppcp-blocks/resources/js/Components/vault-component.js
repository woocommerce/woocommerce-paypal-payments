import { useEffect, useRef, useState } from '@wordpress/element';
import { loadPayPalScript } from '@ppcp-button/Helper/PayPalScriptLoading';

const namespace = 'ppcpBlocksPaypalExpressButtons';

export const VaultComponent = ( { config, onRenderError } ) => {
	const containerRef = useRef( null );
	const vaultInstanceRef = useRef( null );
	const [ sdkReady, setSdkReady ] = useState( false );
	const [ renderFailed, setRenderFailed ] = useState( false );

	// Load SDK if not already loaded.
	useEffect( () => {
		const paypal = window[ namespace ];
		if ( paypal?.Vault ) {
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

	// Render paypal.Vault() once SDK is ready.
	useEffect( () => {
		if (
			! sdkReady ||
			! containerRef.current ||
			vaultInstanceRef.current
		) {
			return;
		}

		const paypal = window[ namespace ];
		if ( ! paypal?.Vault ) {
			console.error( 'PayPal Vault SDK component not available.' );
			setRenderFailed( true );
			onRenderError?.();
			return;
		}

		try {
			vaultInstanceRef.current = paypal.Vault( {
				createOrder: async () => {
					// Edit flow — wired in Ticket 3.
					throw new Error( 'Edit flow not yet implemented.' );
				},
				onApprove: async ( data ) => {
					// Path A — wired in Ticket 3.
					console.log( 'Vault onApprove:', data );
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
