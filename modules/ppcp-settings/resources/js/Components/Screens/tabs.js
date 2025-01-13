import { __ } from '@wordpress/i18n';
import TabOverview from './Overview/TabOverview';
import TabPaymentMethods from './Overview/TabPaymentMethods';
import TabSettings from './Overview/TabSettings';
import TabStyling from './Overview/TabStyling';
import TabPayLaterMessaging from './Overview/TabPayLaterMessaging';

export const getSettingsTabs = () => {
	const tabs = [];

	tabs.push( {
		name: 'overview',
		title: __( 'Overview', 'woocommerce-paypal-payments' ),
		component: <TabOverview />,
	} );

	tabs.push( {
		name: 'payment-methods',
		title: __( 'Payment Methods', 'woocommerce-paypal-payments' ),
		component: <TabPaymentMethods />,
	} );

	tabs.push( {
		name: 'settings',
		title: __( 'Settings', 'woocommerce-paypal-payments' ),
		component: <TabSettings />,
	} );

	tabs.push( {
		name: 'styling',
		title: __( 'Styling', 'woocommerce-paypal-payments' ),
		component: <TabStyling />,
	} );

	tabs.push( {
		name: 'pay-later-messaging',
		title: __( 'Pay Later Messaging', 'woocommerce-paypal-payments' ),
		component: <TabPayLaterMessaging />,
	} );

	return tabs;
};
