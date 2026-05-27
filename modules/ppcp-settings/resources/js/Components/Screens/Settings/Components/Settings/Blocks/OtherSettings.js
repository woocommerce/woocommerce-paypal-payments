import { __ } from '@wordpress/i18n';
import Accordion from '@ppcp-settings/Components/ReusableComponents/AccordionSection';
import SettingsBlock from '@ppcp-settings/Components/ReusableComponents/SettingsBlock';
import {
	ControlSelect,
	ControlRadioGroup,
	ControlTextInput,
	ControlToggleButton,
} from '@ppcp-settings/Components/ReusableComponents/Controls';
import { SettingsHooks } from '@ppcp-settings/data';
import { useMerchantInfo } from '@ppcp-settings/data/common/hooks';

const OtherSettings = () => {
	const {
		disabledCards,
		setDisabledCards,
		threeDSecure,
		setThreeDSecure,
		paymentLevelProcessing,
		setPaymentLevelProcessing,
		shipsFromPostalCode,
		setShipsFromPostalCode,
	} = SettingsHooks.useSettings();
	const { features } = useMerchantInfo();

	const disabledCardChoices = window.ppcpSettings.disabledCardsChoices;
	const threeDSecureOptions = window.ppcpSettings.threeDSecureOptions;
	const storePostcode = window.ppcpSettings.storePostcode;
	const isEligibleForPaymentLevelProcessing =
		window.ppcpSettings.isEligibleForPaymentLevelProcessing;

	return (
		<Accordion
			title={ __(
				'Other payment method settings',
				'woocommerce-paypal-payments'
			) }
			description={ __(
				'Modify the checkout experience for alternative payment methods, credit cards, and digital wallets.',
				'woocommerce-paypal-payments'
			) }
		>
			{ features.advanced_credit_and_debit_cards.enabled && (
				<SettingsBlock
					title={ __(
						'Disable specific credit cards',
						'woocommerce-paypal-payments'
					) }
					description={ __(
						'By default, all possible credit cards will be accepted. Card types added here will be rejected at checkout.',
						'woocommerce-paypal-payments'
					) }
				>
					<ControlSelect
						options={ disabledCardChoices }
						value={ disabledCards }
						onChange={ setDisabledCards }
						isMulti={ true }
						placeholder={ __(
							'Show all cards',
							'woocommerce-paypal-payments'
						) }
					/>
				</SettingsBlock>
			) }

			<SettingsBlock
				title={ __( '3D Secure', 'woocommerce-paypal-payments' ) }
				description={ __(
					'Authenticate cardholders through their card issuers to reduce fraud and improve transaction security. Successful 3D Secure authentication can shift liability for fraudulent chargebacks to the card issuer.',
					'woocommerce-paypal-payments'
				) }
			>
				<ControlRadioGroup
					options={ threeDSecureOptions }
					value={ threeDSecure }
					onChange={ setThreeDSecure }
				/>
			</SettingsBlock>

			{ isEligibleForPaymentLevelProcessing && (
				<SettingsBlock
					title={ __(
						'Level 2/Level 3 Payment Processing',
						'woocommerce-paypal-payments'
					) }
					description={ __(
						'Reduce transaction fees on business card purchases by automatically sending detailed order data to PayPal. This helps you qualify for lower interchange rates. Available for US merchants processing USD with Visa and Mastercard.',
						'woocommerce-paypal-payments'
					) }
				>
					<SettingsBlock>
						<ControlToggleButton
							id="ppcp-payment-processing"
							label={ __(
								'Enable Level 2/Level 3 Processing',
								'woocommerce-paypal-payments'
							) }
							onChange={ setPaymentLevelProcessing }
							value={ paymentLevelProcessing }
						/>
					</SettingsBlock>
					<SettingsBlock
						title={ __(
							'Shipping Origin ZIP Code',
							'woocommerce-paypal-payments'
						) }
						description={ __(
							'Enter the ZIP code where you ship orders from. Use your warehouse or fulfillment center location if different from your business address.',
							'woocommerce-paypal-payments'
						) }
					>
						<ControlTextInput
							value={ shipsFromPostalCode }
							onChange={ setShipsFromPostalCode }
							placeholder={
								storePostcode ||
								__( 'ZIP code', 'woocommerce-paypal-payments' )
							}
						/>
					</SettingsBlock>
				</SettingsBlock>
			) }
		</Accordion>
	);
};

export default OtherSettings;
