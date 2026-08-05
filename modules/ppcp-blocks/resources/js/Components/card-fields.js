import { useEffect, useRef, useState } from '@wordpress/element';

import {
	PayPalScriptProvider,
	PayPalCardFieldsProvider,
	PayPalNameField,
	PayPalNumberField,
	PayPalExpiryField,
	PayPalCVVField,
} from '@paypal/react-paypal-js';

import { CheckoutHandler } from './checkout-handler';
import {
	createOrder,
	onApprove,
	createVaultSetupToken,
	onApproveSavePayment,
} from '../card-fields-config';
import { cartHasSubscriptionProducts } from '../Helper/Subscription';
import { __ } from '@wordpress/i18n';

export function CardFields( { config, eventRegistration, emitResponse } ) {
	const { onPaymentSetup } = eventRegistration;
	const { responseTypes } = emitResponse;

	const [ cardFieldsForm, setCardFieldsForm ] = useState();
	const getCardFieldsForm = ( cardFieldsForm ) => {
		setCardFieldsForm( cardFieldsForm );
	};

	const hasSubscriptionProducts = cartHasSubscriptionProducts(
		config.scriptData
	);

	// A subscription always needs the card vaulted to pay its renewals; otherwise
	// the buyer decides via the checkbox. Kept in a ref so that every submit reads
	// the current value: an attempt that fails checkout validation must not change
	// what the next attempt asks for.
	const savePayment = useRef( hasSubscriptionProducts );
	const getSavePayment = ( value ) => {
		savePayment.current = value;
	};

	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					try {
						await cardFieldsForm.submit();
					} catch ( error ) {
						console.error( error );
						return {
							type: responseTypes.ERROR,
							message:
								config.scriptData.hosted_fields.labels
									.fields_not_valid,
						};
					}

					return {
						type: responseTypes.SUCCESS,
					};
				}

				return handlePaymentProcessing();
			} ),
		[ onPaymentSetup, cardFieldsForm ]
	);

	return (
		<>
			<PayPalScriptProvider
				options={ {
					clientId: config.scriptData.client_id,
					components: 'card-fields',
					dataNamespace: 'ppcp-block-card-fields',
					sdkBaseUrl: config.scriptData.script_attributes?.sdkBaseUrl,
				} }
			>
				<PayPalCardFieldsProvider
					createVaultSetupToken={
						config.scriptData.is_free_trial_cart
							? createVaultSetupToken
							: undefined
					}
					createOrder={
						config.scriptData.is_free_trial_cart
							? undefined
							: () => createOrder( savePayment.current )
					}
					onApprove={
						config.scriptData.is_free_trial_cart
							? onApproveSavePayment
							: onApprove
					}
					onError={ ( err ) => {
						console.error( err );
					} }
				>
					{ config.name_on_card === 'yes' && (
						<PayPalNameField
							placeholder={ __(
								'Cardholder Name (optional)',
								'woocommerce-paypal-payments'
							) }
						/>
					) }
					<PayPalNumberField
						placeholder={ __(
							'Card number',
							'woocommerce-paypal-payments'
						) }
					/>
					<div style={ { display: 'flex', width: '100%' } }>
						<div style={ { width: '100%' } }>
							<PayPalExpiryField
								placeholder={ __(
									'MM / YY',
									'woocommerce-paypal-payments'
								) }
							/>
						</div>
						<div style={ { width: '100%' } }>
							<PayPalCVVField
								placeholder={ __(
									'CVV',
									'woocommerce-paypal-payments'
								) }
							/>
						</div>
					</div>
					<CheckoutHandler
						getCardFieldsForm={ getCardFieldsForm }
						getSavePayment={ getSavePayment }
						hasSubscriptionProducts={ hasSubscriptionProducts }
						saveCardText={ config.save_card_text }
						is_vaulting_enabled={ config.is_vaulting_enabled }
					/>
				</PayPalCardFieldsProvider>
			</PayPalScriptProvider>
		</>
	);
}
