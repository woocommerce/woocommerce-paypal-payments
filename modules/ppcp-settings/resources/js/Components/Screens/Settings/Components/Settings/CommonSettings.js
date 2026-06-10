import { __ } from '@wordpress/i18n';

import SettingsCard from '@ppcp-settings/Components/ReusableComponents/SettingsCard';
import OrderIntent from './Blocks/OrderIntent';
import SavePaymentMethods from './Blocks/SavePaymentMethods';
import InvoicePrefix from './Blocks/InvoicePrefix';
import PayNowExperience from './Blocks/PayNowExperience';
import StayUpdated from './Blocks/StayUpdated';
import { useRegisteredSettings, SLOTS } from '@ppcp-settings/extensions';

const CommonSettings = ( { ownBrandOnly } ) => {
	// Get registered settings for common settings
	const footerSettings = useRegisteredSettings( SLOTS.COMMON_SETTINGS_END );

	return (
		<SettingsCard
			icon="icon-settings-common.svg"
			title={ __( 'Common settings', 'woocommerce-paypal-payments' ) }
			className="ppcp-r-settings-card ppcp-r-settings-card--common-settings"
			description={ __(
				'Customize key features to tailor your PayPal experience.',
				'woocommerce-paypal-payments'
			) }
		>
			<InvoicePrefix />
			<OrderIntent />
			<SavePaymentMethods ownBradOnly={ ownBrandOnly } />
			<PayNowExperience />
			<StayUpdated />

			{ /* Extension point */ }
			{ footerSettings.map( ( { component: Component, id } ) => (
				<Component key={ id } />
			) ) }
		</SettingsCard>
	);
};

export default CommonSettings;
