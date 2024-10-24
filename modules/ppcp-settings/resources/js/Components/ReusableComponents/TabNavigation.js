import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const onSelect = ( tabName ) => {
	console.log( 'Selecting tab', tabName );
};

const TabNavigation = () => {
	return (
		<TabPanel
			className="my-tab-panel"
			activeClass="active-tab"
			onSelect={ onSelect }
			tabs={ [
				{
					name: 'dashboard',
					title: __( 'Dashboard', 'woocommerce-paypal-payments' ),
					className: 'ppcp-r-tab-dashboard',
				},
				{
					name: 'payment-methods',
					title: __(
						'Payment Methods',
						'woocommerce-paypal-payments'
					),
					className: 'ppcp-r-tab-payment-methods',
				},
				{
					name: 'settings',
					title: __( 'Settings', 'woocommerce-paypal-payments' ),
					className: 'ppcp-r-tab-settings',
				},
				{
					name: 'styling',
					title: __( 'Styling', 'woocommerce-paypal-payments' ),
					className: 'ppcp-r-tab-styling',
				},
			] }
		>
			{ ( tab ) => <p>{ tab.title }</p> }
		</TabPanel>
	);
};

export default TabNavigation;
