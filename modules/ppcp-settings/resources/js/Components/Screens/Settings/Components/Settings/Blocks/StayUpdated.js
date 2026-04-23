import { __ } from '@wordpress/i18n';

import { ControlToggleButton } from '@ppcp-settings/Components/ReusableComponents/Controls';
import { SettingsHooks } from '@ppcp-settings/data';
import SettingsBlock from '@ppcp-settings/Components/ReusableComponents/SettingsBlock';

const StayUpdated = () => {
	const { stayUpdated, setStayUpdated } = SettingsHooks.useSettings();

	return (
		<SettingsBlock className="ppcp--pay-now-experience">
			<ControlToggleButton
				id="ppcp-stay-updated"
				label={ __( 'Stay Updated', 'woocommerce-paypal-payments' ) }
				description={ __(
					'Get the latest PayPal features and capabilities as they are released. When the extension is updated, new features, payment methods, styling options, and more will automatically update.',
					'woocommerce-paypal-payments'
				) }
				onChange={ setStayUpdated }
				value={ stayUpdated }
			/>
		</SettingsBlock>
	);
};

export default StayUpdated;
