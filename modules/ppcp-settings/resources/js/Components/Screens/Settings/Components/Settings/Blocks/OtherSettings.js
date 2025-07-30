import { __ } from '@wordpress/i18n';
import Accordion from '../../../../../ReusableComponents/AccordionSection';
import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';
import {
	ControlSelect,
	ControlRadioGroup,
} from '../../../../../ReusableComponents/Controls';
import { SettingsHooks } from '../../../../../../data';

const OtherSettings = () => {
	const { disabledCards, setDisabledCards, threeDSecure, setThreeDSecure } =
		SettingsHooks.useSettings();

	const disabledCardChoices = window.ppcpSettings.disabledCardsChoices;
	const threeDSecureOptions = window.ppcpSettings.threeDSecureOptions;

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
		</Accordion>
	);
};

export default OtherSettings;
