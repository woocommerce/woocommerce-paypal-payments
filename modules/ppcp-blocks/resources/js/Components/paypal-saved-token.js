// TODO (next iteration): relocate to the ppcp-vault-component module once it has a
// JS build entry.
import {
	useEffect,
	useLayoutEffect,
	useState,
	useCallback,
	createPortal,
} from '@wordpress/element';
import { VaultComponent } from './vault-component';

const VAULT_CONTAINER_ID = 'ppcp-vault-component';

const findSavedTokenRow = ( wcTokenId ) => {
	if ( ! wcTokenId ) {
		return null;
	}
	const input = document.querySelector(
		`input[name="radio-control-wc-payment-method-saved-tokens"][value="${ wcTokenId }"]`
	);
	return (
		input?.closest( '.wc-block-components-radio-control__option' ) ?? null
	);
};

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
	const [ portalTarget, setPortalTarget ] = useState( null );

	const vaultData = config?.scriptData?.vault_component;
	const isVaultEligible = vaultData?.is_eligible === true;
	const isSavedTokenSelected = token && token !== '0' && token !== 0;
	// A zero-total subscription cart (free trial or 100% coupon) must use the
	// save-without-purchase flow. The Vault Component is order-based and would
	// create a $0 order, which PayPal rejects with CANNOT_BE_ZERO_OR_NEGATIVE.
	const isFreeTrial = config?.scriptData?.is_free_trial_cart === true;
	const shouldShowVaultComponent =
		isVaultEligible &&
		isSavedTokenSelected &&
		! isFreeTrial &&
		! vaultRenderFailed;

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

	useLayoutEffect( () => {
		if ( ! shouldShowVaultComponent ) {
			setPortalTarget( null );
			return undefined;
		}

		const row = findSavedTokenRow( vaultData?.wc_token_id );
		if ( ! row ) {
			setPortalTarget( null );
			return undefined;
		}

		const label =
			row.querySelector(
				'.wc-block-components-radio-control__option-layout'
			) ??
			row.querySelector( '.wc-block-components-radio-control__label' );
		const previousLabelDisplay = label?.style.display ?? '';
		if ( label ) {
			label.style.display = 'none';
		}

		const container = document.createElement( 'div' );
		container.id = VAULT_CONTAINER_ID;
		row.appendChild( container );
		setPortalTarget( container );

		return () => {
			if ( label ) {
				label.style.display = previousLabelDisplay;
			}
			container.remove();
		};
	}, [ shouldShowVaultComponent, vaultData?.wc_token_id ] );

	if ( ! shouldShowVaultComponent || ! portalTarget ) {
		return null;
	}

	return createPortal(
		<VaultComponent
			config={ config }
			onApproveOrder={ handleApproveOrder }
			onRenderError={ handleVaultRenderError }
		/>,
		portalTarget
	);
};
