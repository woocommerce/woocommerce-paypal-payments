import { __ } from '@wordpress/i18n';
import { CommonHooks } from '../../data';

const ConnectionInfo = () => {
	const { merchant } = CommonHooks.useMerchantInfo();

	return (
		<div className="ppcp-r-connection-status__data">
			<StatusRow
				label={ __( 'Merchant ID', 'woocommerce-paypal-payments' ) }
				value={ merchant.id }
			/>
			<StatusRow
				label={ __( 'Email address', 'woocommerce-paypal-payments' ) }
				value={ merchant.email }
			/>
			<StatusRow
				label={ __( 'Client ID', 'woocommerce-paypal-payments' ) }
				value={ merchant.clientId }
			/>
		</div>
	);
};
export default ConnectionInfo;

const StatusRow = ( { label, value } ) => (
	<div className="ppcp-r-connection-status__status-row">
		<span className="ppcp-r-connection-status__status-label">
			{ label }
		</span>
		<span className="ppcp-r-connection-status__status-value">
			{ value }
		</span>
	</div>
);
