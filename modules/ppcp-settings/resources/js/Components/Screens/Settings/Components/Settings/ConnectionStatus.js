import { __ } from '@wordpress/i18n';
import classNames from 'classnames';

import SettingsCard from '../../../../ReusableComponents/SettingsCard';
import { CommonHooks } from '../../../../../data';
import ConnectionStatusBadge from './Parts/ConnectionStatusBadge';
import DisconnectButton from './Parts/DisconnectButton';
import SettingsBlock from '../../../../ReusableComponents/SettingsBlock';
import { ControlStaticValue } from '../../../../ReusableComponents/Controls';
import { CardActions } from '../../../../ReusableComponents/Elements';

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
			<CardActions isDimmed={ true }>
				<DisconnectButton />
			</CardActions>
		</>
	);
};
