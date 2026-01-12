import { __ } from '@wordpress/i18n';
import Accordion from '@ppcp-settings/Components/ReusableComponents/AccordionSection';
import SettingsBlock from '@ppcp-settings/Components/ReusableComponents/SettingsBlock';
import {
	ControlSelect,
	ControlRadioGroup,
	ControlTextInput,
} from '@ppcp-settings/Components/ReusableComponents/Controls';
import { SettingsHooks } from '@ppcp-settings/data';
import { useMerchantInfo } from '@ppcp-settings/data/common/hooks';

const OtherSettings = () => {
	const {
		disabledCards,
		setDisabledCards,
		threeDSecure,
		setThreeDSecure,
		shipsFromPostalCode,
		setShipsFromPostalCode,
	} = SettingsHooks.useSettings();
	const { features } = useMerchantInfo();

	const disabledCardChoices = window.ppcpSettings.disabledCardsChoices;
	const threeDSecureOptions = window.ppcpSettings.threeDSecureOptions;
	const storePostcode = window.ppcpSettings.storePostcode;

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

			<SettingsBlock
				title={ __(
					'Level 2/Level 3 Payment Processing',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					'Qualify for lower interchange rates on corporate and purchase card transactions by sending additional transaction details to PayPal. Level 2/3 processing is available for US merchants processing USD transactions with Visa and Mastercard.',
					'woocommerce-paypal-payments'
				) }
			>
				<SettingsBlock
					title={ __(
						'Ship-from ZIP code',
						'woocommerce-paypal-payments'
					) }
					description={ __(
						'Enter the postal code of the location where you ship products from. This is required for Level 3 processing and may differ from your store address if you use a warehouse or fulfillment center.',
						'woocommerce-paypal-payments'
					) }
				>
					<ControlTextInput
						value={ shipsFromPostalCode }
						onChange={ setShipsFromPostalCode }
						placeholder={
							storePostcode ||
							__(
								'Ship-from ZIP code',
								'woocommerce-paypal-payments'
							)
						}
					/>
				</SettingsBlock>
			</SettingsBlock>
		</Accordion>
	);
};

export default OtherSettings;
