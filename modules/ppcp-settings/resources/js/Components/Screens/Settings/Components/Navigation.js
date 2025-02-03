import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import TopNavigation from '../../../ReusableComponents/TopNavigation';
import { useSaveSettings } from '../../../../hooks/useSaveSettings';
import TabBar from '../../../ReusableComponents/TabBar';

const SettingsNavigation = ( {
	canSave = true,
	tabs,
	activePanel,
	setActivePanel,
} ) => {
	const { persistAll } = useSaveSettings();

	const title = __( 'PayPal Payments', 'woocommerce-paypal-payments' );

	return (
		<TopNavigation
			title={ title }
			exitOnTitleClick={ true }
			subNavigation={
				<TabBar
					tabs={ tabs }
					activePanel={ activePanel }
					setActivePanel={ setActivePanel }
				/>
			}
		>
			{ canSave && (
				<Button variant="primary" onClick={ persistAll }>
					{ __( 'Save', 'woocommerce-paypal-payments' ) }
				</Button>
			) }
		</TopNavigation>
	);
};

export default SettingsNavigation;
