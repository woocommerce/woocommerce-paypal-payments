import { __ } from '@wordpress/i18n';

import SettingsCard from '../../../../ReusableComponents/SettingsCard';
import { CommonHooks } from '../../../../../data';
import ConnectionStatusBadge from './Parts/ConnectionStatusBadge';
import DisconnectButton from './Parts/DisconnectButton';
import SettingsBlock from '../../../../ReusableComponents/SettingsBlock';
import { ControlStaticValue } from '../../../../ReusableComponents/Controls';

const ConnectionStatus = () => {
	const { merchant } = CommonHooks.useMerchantInfo();

	return (
		<SettingsCard
			className="ppcp-connection-details ppcp--value-list"
			title={ __( 'Connection status', 'woocommerce-paypal-payments' ) }
			description={ <ConnectionDescription /> }
		>
			<SettingsBlock>
				<ControlStaticValue
					value={
						<ConnectionStatusBadge
							isActive={ merchant.isConnected }
							isSandbox={ merchant.isSandbox }
						/>
					}
				/>
			</SettingsBlock>
			<SettingsBlock
				title={ __( 'Merchant ID', 'woocommerce-paypal-payments' ) }
			>
				<ControlStaticValue value={ merchant.id } />
			</SettingsBlock>
			<SettingsBlock
				title={ __( 'Email address', 'woocommerce-paypal-payments' ) }
			>
				<ControlStaticValue value={ merchant.email } />
			</SettingsBlock>
			<SettingsBlock
				title={ __( 'Client ID', 'woocommerce-paypal-payments' ) }
			>
				<ControlStaticValue value={ merchant.clientId } />
			</SettingsBlock>
		</SettingsCard>
	);
};

export default ConnectionStatus;

const ConnectionDescription = () => {
	return (
		<>
			{ __(
				'Your PayPal account connection details.',
				'woocommerce-paypal-payments'
			) }
			<DisconnectButton />
		</>
	);
};
