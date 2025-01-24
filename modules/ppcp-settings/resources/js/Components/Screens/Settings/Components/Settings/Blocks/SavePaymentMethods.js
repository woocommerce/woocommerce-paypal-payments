import { __, sprintf } from '@wordpress/i18n';

import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';
import { ControlToggleButton } from '../../../../../ReusableComponents/Controls';
import { SettingsHooks } from '../../../../../../data';

const SavePaymentMethods = () => {
	const {
		savePaypalAndVenmo,
		setSavePaypalAndVenmo,
		saveCardDetails,
		setSaveCardDetails,
	} = SettingsHooks.useSettings();

	return (
		<SettingsBlock
			title={ __(
				'Save payment methods',
				'woocommerce-paypal-payments'
			) }
			description={ __(
				"Securely store customers' payment methods for future payments and subscriptions, simplifying checkout and enabling recurring transactions.",
				'woocommerce-paypal-payments'
			) }
			className="ppcp--save-payment-methods"
		>
			<ControlToggleButton
				label={ __(
					'Save PayPal and Venmo',
					'woocommerce-paypal-payments'
				) }
				description={ sprintf(
					/* translators: 1: URL to Pay Later documentation, 2: URL to Alternative Payment Methods documentation */
					__(
						'Securely store your customers\' PayPal accounts for a seamless checkout experience. <br />This will disable all <a target="_blank" rel="noreferrer" href="%1$s">Pay Later</a> features and <a target="_blank" rel="noreferrer" href="%2$s">Alternative Payment Methods</a> on your site.',
						'woocommerce-paypal-payments'
					),
					'https://woocommerce.com/document/woocommerce-paypal-payments/#pay-later',
					'https://woocommerce.com/document/woocommerce-paypal-payments/#alternative-payment-methods'
				) }
				value={ savePaypalAndVenmo }
				onChange={ setSavePaypalAndVenmo }
			/>

			<ControlToggleButton
				label={ __(
					'Save Credit and Debit Cards',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					"Securely store your customer's credit card.",
					'woocommerce-paypal-payments'
				) }
				onChange={ setSaveCardDetails }
				value={ saveCardDetails }
			/>
		</SettingsBlock>
	);
};

export default SavePaymentMethods;
