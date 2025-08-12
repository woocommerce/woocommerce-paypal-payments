import { __ } from '@wordpress/i18n';

import BusyStateWrapper from '../../../ReusableComponents/BusyStateWrapper';
import SettingsToggleBlock from '../../../ReusableComponents/SettingsToggleBlock';
import { useSandboxConnection } from '../../../../hooks/useHandleConnections';
import ConnectionButton from './ConnectionButton';

const SandboxConnectionForm = () => {
	const { isSandboxMode, setSandboxMode } = useSandboxConnection();

	const handleToggle = ( isEnabled ) => {
		setSandboxMode( isEnabled, 'user' );
	};

	return (
		<BusyStateWrapper>
			<SettingsToggleBlock
				label={ __(
					'Enable Sandbox Mode',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					'Activate Sandbox mode to safely test PayPal with sample data. Once your store is ready to go live, you can easily switch to your production account.',
					'woocommerce-paypal-payments'
				) }
				isToggled={ !! isSandboxMode }
				setToggled={ handleToggle }
			>
				<ConnectionButton
					title={ __(
						'Connect Account',
						'woocommerce-paypal-payments'
					) }
					showIcon={ false }
					variant="secondary"
					className="small-button"
					isSandbox={
						true /* This button always connects to sandbox */
					}
				/>
			</SettingsToggleBlock>
		</BusyStateWrapper>
	);
};

export default SandboxConnectionForm;
