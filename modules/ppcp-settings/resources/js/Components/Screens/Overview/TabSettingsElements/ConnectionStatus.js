import { __ } from '@wordpress/i18n';
import SettingsCard from '../../../ReusableComponents/SettingsCard';
import { CommonHooks } from '../../../../data';
import TitleBadge, {
	TITLE_BADGE_NEGATIVE,
	TITLE_BADGE_POSITIVE,
} from '../../../ReusableComponents/TitleBadge';
import ConnectionInfo from '../../../ReusableComponents/ConnectionInfo';
const ConnectionStatus = () => {
	const { merchant } = CommonHooks.useMerchantInfo();
	return (
		<SettingsCard
			className="ppcp-r-tab-overview-support"
			title={ __( 'Connection status', 'woocommerce-paypal-payments' ) }
			description={ __(
				'Your PayPal account connection details',
				'woocommerce-paypal-payments'
			) }
		>
			<div className="ppcp-r-connection-status">
				<div className="ppcp-r-connection-status__status">
					<div className="ppcp-r-connection-status__status-status">
						{ merchant.isConnected ? (
							<TitleBadge
								type={ TITLE_BADGE_POSITIVE }
								text={ __(
									'Active',
									'woocommerce-paypal-payments'
								) }
							/>
						) : (
							<TitleBadge
								type={ TITLE_BADGE_NEGATIVE }
								text={ __(
									'Not Activated',
									'woocommerce-paypal-payments'
								) }
							/>
						) }
					</div>
				</div>
				{ merchant.isConnected && (
					<ConnectionInfo connectionStatusDataDefault={ merchant } />
				) }
			</div>
		</SettingsCard>
	);
};
export default ConnectionStatus;
