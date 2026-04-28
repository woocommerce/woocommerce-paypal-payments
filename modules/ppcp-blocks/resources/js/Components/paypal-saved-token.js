import { useEffect, useState, useCallback } from '@wordpress/element';
import { VaultComponent } from './vault-component';

export const PayPalSavedToken = ( {
	config,
	token,
	eventRegistration,
	emitResponse,
} ) => {
	const { onPaymentSetup } = eventRegistration;
	const { responseTypes } = emitResponse;
	const [ approvedOrderId, setApprovedOrderId ] = useState( null );
	const [ vaultRenderFailed, setVaultRenderFailed ] = useState( false );

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

				return { type: responseTypes.SUCCESS };
			} ),
		[ onPaymentSetup, responseTypes, approvedOrderId ]
	);

	if ( ! shouldShowVaultComponent ) {
		return null;
	}

	return (
		<VaultComponent
			config={ config }
			onApproveOrder={ handleApproveOrder }
			onRenderError={ handleVaultRenderError }
		/>
	);
};
