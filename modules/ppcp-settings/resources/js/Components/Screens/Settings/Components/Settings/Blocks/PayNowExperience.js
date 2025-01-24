import { __ } from '@wordpress/i18n';

import { ControlToggleButton } from '../../../../../ReusableComponents/Controls';
import { SettingsHooks } from '../../../../../../data';
import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';

const PayNowExperience = () => {
	const { payNowExperience, setPayNowExperience } =
		SettingsHooks.useSettings();

	return (
		<SettingsBlock className="ppcp--pay-now-experience">
			<ControlToggleButton
				label={ __(
					'Pay Now Experience',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					'Let PayPal customers skip the Order Review page by selecting shipping options directly within PayPal.',
					'woocommerce-paypal-payments'
				) }
				onChange={ setPayNowExperience }
				value={ payNowExperience }
			/>
		</SettingsBlock>
	);
};

export default PayNowExperience;
