import { __ } from '@wordpress/i18n';
import { selectTab, TAB_IDS } from '../../utils/tabSelector';

export const todosData = [
	{
		id: 'enable_fastlane',
		title: __( 'Enable Fastlane', 'woocommerce-paypal-payments' ),
		description: __(
			'Accelerate your guest checkout with Fastlane by PayPal.',
			'woocommerce-paypal-payments'
		),
		isCompleted: () => {
			return false;
		},
		onClick: () => {
			selectTab( TAB_IDS.PAYMENT_METHODS, 'ppcp-card-payments-card' );
		},
	},
	{
		id: 'enable_credit_debit_cards',
		title: __(
			'Enable Credit and Debit Cards on your checkout',
			'woocommerce-paypal-payments'
		),
		description: __(
			'Credit and Debit Cards is now available for Blocks checkout pages.',
			'woocommerce-paypal-payments'
		),
		isCompleted: () => {
			return false;
		},
		onClick: () => {
			selectTab( TAB_IDS.PAYMENT_METHODS, 'ppcp-card-payments-card' );
		},
	},
	{
		id: 'enable_pay_later_messaging',
		title: __(
			'Enable Pay Later messaging',
			'woocommerce-paypal-payments'
		),
		description: __(
			'Show Pay Later messaging to boost conversion rate and increase cart size.',
			'woocommerce-paypal-payments'
		),
		isCompleted: () => {
			return false;
		},
		onClick: () => {
			selectTab( TAB_IDS.OVERVIEW, 'pay_later_messaging' );
		},
	},
	{
		id: 'configure_paypal_subscription',
		title: __(
			'Configure a PayPal Subscription',
			'woocommerce-paypal-payments'
		),
		description: __(
			'Connect a subscriptions-type product from WooCommerce with PayPal.',
			'woocommerce-paypal-payments'
		),
		isCompleted: () => {
			return false;
		},
		onClick: () => {
			console.log(
				'Take merchant to product list, filtered with subscription-type products'
			);
		},
	},
	{
		id: 'register_domain_apple_pay',
		title: __(
			'Register Domain for Apple Pay',
			'woocommerce-paypal-payments'
		),
		description: __(
			'To enable Apple Pay, you must register your domain with PayPal.',
			'woocommerce-paypal-payments'
		),
		isCompleted: () => {
			return false;
		},
		onClick: () => {
			selectTab( TAB_IDS.OVERVIEW, 'apple_pay' );
		},
	},
	{
		id: 'add_digital_wallets_to_account',
		title: __(
			'Add digital wallets to your account',
			'woocommerce-paypal-payments'
		),
		description: __(
			'Add the ability to accept Apple Pay & Google Pay to your PayPal account.',
			'woocommerce-paypal-payments'
		),
		isCompleted: () => {
			return false;
		},
		onClick: () => {
			console.log(
				'Take merchant to PayPal to enable Apple Pay & Google Pay'
			);
		},
	},
	{
		id: 'add_apple_pay_to_account',
		title: __(
			'Add Apple Pay to your account',
			'woocommerce-paypal-payments'
		),
		description: __(
			'Add the ability to accept Apple Pay to your PayPal account.',
			'woocommerce-paypal-payments'
		),
		isCompleted: () => {
			return false;
		},
		onClick: () => {
			console.log( 'Take merchant to PayPal to enable Apple Pay' );
		},
	},
	{
		id: 'add_google_pay_to_account',
		title: __(
			'Add Google Pay to your account',
			'woocommerce-paypal-payments'
		),
		description: __(
			'Add the ability to accept Google Pay to your PayPal account.',
			'woocommerce-paypal-payments'
		),
		isCompleted: () => {
			return false;
		},
		onClick: () => {
			console.log( 'Take merchant to PayPal to enable Google Pay' );
		},
	},
	{
		id: 'enable_apple_pay',
		title: __( 'Enable Apple Pay', 'woocommerce-paypal-payments' ),
		description: __(
			'Allow your buyers to check out via Apple Pay.',
			'woocommerce-paypal-payments'
		),
		isCompleted: () => {
			return false;
		},
		onClick: () => {
			selectTab( TAB_IDS.OVERVIEW, 'apple_pay' );
		},
	},
	{
		id: 'enable_google_pay',
		title: __( 'Enable Google Pay', 'woocommerce-paypal-payments' ),
		description: __(
			'Allow your buyers to check out via Google Pay.',
			'woocommerce-paypal-payments'
		),
		isCompleted: () => {
			return false;
		},
		onClick: () => {
			selectTab( TAB_IDS.OVERVIEW, 'google_pay' );
		},
	},
];
