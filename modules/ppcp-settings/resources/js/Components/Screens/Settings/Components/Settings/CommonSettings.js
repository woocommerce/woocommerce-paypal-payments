import { __ } from '@wordpress/i18n';

import SettingsCard from '../../../../ReusableComponents/SettingsCard';
import OrderIntent from './Blocks/OrderIntent';
import SavePaymentMethods from './Blocks/SavePaymentMethods';
import InvoicePrefix from './Blocks/InvoicePrefix';
import PayNowExperience from './Blocks/PayNowExperience';

const CommonSettings = () => (
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
		<SavePaymentMethods />
		<PayNowExperience />
	</SettingsCard>
);

export default CommonSettings;
