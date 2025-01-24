import { __ } from '@wordpress/i18n';

import { ControlToggleButton } from '../../../../../ReusableComponents/Controls';
import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';
import { SettingsHooks } from '../../../../../../data';

const OrderIntent = () => {
	const {
		authorizeOnly,
		setAuthorizeOnly,
		captureVirtualOnlyOrders,
		setCaptureVirtualOnlyOrders,
	} = SettingsHooks.useSettings();

	return (
		<SettingsBlock
			title={ __( 'Order Intent', 'woocommerce-paypal-payments' ) }
			description={ __(
				'Choose between immediate capture or authorization-only, with manual capture in the Order section.',
				'woocommerce-paypal-payments'
			) }
			className="ppcp--order-intent"
		>
			<ControlToggleButton
				label={ __( 'Authorize Only', 'woocommerce-paypal-payments' ) }
				onChange={ setAuthorizeOnly }
				value={ authorizeOnly }
			/>

			<ControlToggleButton
				label={ __(
					'Capture Virtual-Only Orders',
					'woocommerce-paypal-payments'
				) }
				onChange={ setCaptureVirtualOnlyOrders }
				value={ captureVirtualOnlyOrders }
			/>
		</SettingsBlock>
	);
};

export default OrderIntent;
