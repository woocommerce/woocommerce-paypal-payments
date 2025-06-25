import { __ } from '@wordpress/i18n';

import { ControlToggleButton } from '../../../../../ReusableComponents/Controls';
import { SettingsHooks } from '../../../../../../data';
import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';

const StayUpdated = () => {
	const { stayUpdated, setStayUpdated } = SettingsHooks.useSettings();

	return (
		<SettingsBlock className="ppcp--pay-now-experience">
			<ControlToggleButton
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
