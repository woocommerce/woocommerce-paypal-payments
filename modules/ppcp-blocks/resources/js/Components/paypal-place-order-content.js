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

	const vaultData = config?.scriptData?.vault_component;
	const isVaultEligible = vaultData?.is_eligible === true;
	const isSavedTokenSelected = token && token !== '0' && token !== 0;
	const shouldShowVaultComponent =
		isVaultEligible && isSavedTokenSelected && ! vaultRenderFailed;

	const handleVaultRenderError = useCallback( () => {
		setVaultRenderFailed( true );
	}, [] );

	useEffect(
		() =>
			onPaymentSetup( () => {
				return { type: responseTypes.SUCCESS };
			} ),
		[ onPaymentSetup, responseTypes ]
	);

	if ( shouldShowVaultComponent ) {
		return (
			<VaultComponent
				config={ config }
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
