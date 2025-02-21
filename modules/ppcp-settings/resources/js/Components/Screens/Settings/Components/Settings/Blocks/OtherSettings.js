import { __ } from '@wordpress/i18n';

import Accordion from '../../../../../ReusableComponents/AccordionSection';
import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';
import { ControlSelect } from '../../../../../ReusableComponents/Controls';
import { SettingsHooks } from '../../../../../../data';

const OtherSettings = () => {
	const { disabledCards, setDisabledCards } = SettingsHooks.useSettings();

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
		</Accordion>
	);
};

export default OtherSettings;

const disabledCardChoices = [
	{ value: '', label: __( 'Select', 'woocommerce-paypal-payments' ) },
	{
		value: 'mastercard',
		label: __( 'Mastercard', 'woocommerce-paypal-payments' ),
	},
	{ value: 'visa', label: __( 'Visa', 'woocommerce-paypal-payments' ) },
	{
		value: 'amex',
		label: __( 'American Express', 'woocommerce-paypal-payments' ),
	},
	{ value: 'jcb', label: __( 'JCB', 'woocommerce-paypal-payments' ) },
	{
		value: 'diners-club',
		label: __( 'Diners Club', 'woocommerce-paypal-payments' ),
	},
];
