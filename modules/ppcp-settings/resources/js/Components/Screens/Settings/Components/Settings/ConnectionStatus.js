import { __ } from '@wordpress/i18n';
import classNames from 'classnames';

import SettingsCard from '@ppcp-settings/Components/ReusableComponents/SettingsCard';
import { CommonHooks } from '@ppcp-settings/data';
import ConnectionStatusBadge from './Parts/ConnectionStatusBadge';
import DisconnectButton from './Parts/DisconnectButton';
import SettingsBlock from '@ppcp-settings/Components/ReusableComponents/SettingsBlock';
import { ControlStaticValue } from '@ppcp-settings/Components/ReusableComponents/Controls';
import { CardActions } from '@ppcp-settings/Components/ReusableComponents/Elements';

const ConnectionStatus = () => {
	const merchant = CommonHooks.useMerchant();
	const className = classNames( 'ppcp-connection-details ppcp--value-list', {
		'ppcp--type-business': merchant.isBusinessSeller,
		'ppcp--type-casual': merchant.isCasualSeller,
	} );

	return (
		<SettingsCard
			className={ className }
			title={ __( 'Connection status', 'woocommerce-paypal-payments' ) }
			description={ <ConnectionDescription /> }
		>
			<SettingsBlock className="ppcp--pull-right">
				<ControlStaticValue
					value={
						<ConnectionStatusBadge
							isActive={ merchant.isConnected }
							isSandbox={ merchant.isSandbox }
							isBusinessSeller={ merchant.isBusinessSeller }
						/>
					}
				/>
			</SettingsBlock>
			<SettingsBlock
				title={ __( 'Merchant ID', 'woocommerce-paypal-payments' ) }
				className="ppcp--no-gap"
			>
				<ControlStaticValue value={ merchant.id } showCopy={ true } />
			</SettingsBlock>
			<SettingsBlock
				title={ __( 'Email address', 'woocommerce-paypal-payments' ) }
			>
				<ControlStaticValue
					value={ merchant.email }
					showCopy={ true }
				/>
			</SettingsBlock>
			<SettingsBlock
				title={ __( 'Client ID', 'woocommerce-paypal-payments' ) }
			>
				<ControlStaticValue
					value={ merchant.clientId }
					showCopy={ true }
				/>
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
			<CardActions isDimmed={ true }>
				<DisconnectButton />
			</CardActions>
		</>
	);
};
