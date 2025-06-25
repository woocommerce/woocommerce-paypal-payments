import { __, sprintf } from '@wordpress/i18n';

import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';
import { ControlToggleButton } from '../../../../../ReusableComponents/Controls';
import { SettingsHooks } from '../../../../../../data';
import { useMerchantInfo } from '../../../../../../data/common/hooks';

const SavePaymentMethods = ( { ownBradOnly } ) => {
	const {
		savePaypalAndVenmo,
		setSavePaypalAndVenmo,
		saveCardDetails,
		setSaveCardDetails,
	} = SettingsHooks.useSettings();

	const { features } = useMerchantInfo();

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
				id="ppcp-save-paypal-and-venmo"
				label={ __(
					'Save PayPal and Venmo',
					'woocommerce-paypal-payments'
				) }
				description={ sprintf(
					/* translators: 1: URL to Pay Later documentation */
					__(
						'Securely store your customers\' PayPal accounts for a seamless checkout experience. <br />This will disable the <a target="_blank" rel="noreferrer" href="%1$s">Pay Later</a> payment method on your site.',
						'woocommerce-paypal-payments'
					),
					'https://woocommerce.com/document/woocommerce-paypal-payments/#pay-later'
				) }
				value={
					features.save_paypal_and_venmo.enabled
						? savePaypalAndVenmo
						: false
				}
				onChange={ setSavePaypalAndVenmo }
				disabled={ ! features.save_paypal_and_venmo.enabled }
			/>

			{ ownBradOnly || (
				// Credit card settings are only available in "white label" mode.
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
			) }
		</SettingsBlock>
	);
};

export default SavePaymentMethods;
