import { useEffect, useState, useCallback } from '@wordpress/element';
import { VaultComponent } from './vault-component';

export const PayPalPlaceOrderContent = ( {
	config,
	description,
	placeOrderButtonDescription,
	eventRegistration,
	emitResponse,
	token,
} ) => {
	const { onPaymentSetup } = eventRegistration;
	const { responseTypes } = emitResponse;
	const [ vaultRenderFailed, setVaultRenderFailed ] = useState( false );
	const [ approvedOrderId, setApprovedOrderId ] = useState( null );

	const vaultData = config?.scriptData?.vault_component;
	const isVaultEligible = vaultData?.is_eligible === true;
	const isSavedTokenSelected = token && token !== '0' && token !== 0;
	const shouldShowVaultComponent =
		isVaultEligible && isSavedTokenSelected && ! vaultRenderFailed;

	const handleVaultRenderError = useCallback( () => {
		setVaultRenderFailed( true );
	}, [] );

	const handleApproveOrder = useCallback( ( orderId ) => {
		setApprovedOrderId( orderId );
	}, [] );

	useEffect(
		() =>
			onPaymentSetup( () => {
				// Customer edited FI, we have an approved order ID.
				if ( approvedOrderId ) {
					return {
						type: responseTypes.SUCCESS,
						meta: {
							paymentMethodData: {
								paypal_order_id: approvedOrderId,
								funding_source: 'paypal',
							},
						},
					};
				}

				// No edit, WC Blocks handles saved token automatically.
				return { type: responseTypes.SUCCESS };
			} ),
		[ onPaymentSetup, responseTypes, approvedOrderId ]
	);

	if ( shouldShowVaultComponent ) {
		return (
			<VaultComponent
				config={ config }
				onApproveOrder={ handleApproveOrder }
				onRenderError={ handleVaultRenderError }
			/>
		);
	}

	if ( placeOrderButtonDescription ) {
		return (
			<div>
				<p dangerouslySetInnerHTML={ { __html: description } } />
				<p
					style={ { textAlign: 'center' } }
					className="ppcp-place-order-description"
					dangerouslySetInnerHTML={ {
						__html: placeOrderButtonDescription,
					} }
				/>
			</div>
		);
	}
	return <div dangerouslySetInnerHTML={ { __html: description } } />;
};
