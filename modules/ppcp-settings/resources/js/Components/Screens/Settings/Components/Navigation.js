import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import TopNavigation from '../../../ReusableComponents/TopNavigation';
import BusyStateWrapper from '../../../ReusableComponents/BusyStateWrapper';
import { useSaveSettings } from '../../../../hooks/useSaveSettings';

const SettingsNavigation = () => {
	const { persistAll } = useSaveSettings();

	const title = __( 'PayPal Payments', 'woocommerce-paypal-payments' );

	return (
		<TopNavigation title={ title } exitOnTitleClick={ true }>
			<BusyStateWrapper>
				<Button variant="primary" onClick={ persistAll }>
					{ __( 'Save', 'woocommerce-paypal-payments' ) }
				</Button>
			</BusyStateWrapper>
		</TopNavigation>
	);
};

export default SettingsNavigation;
