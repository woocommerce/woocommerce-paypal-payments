import { __ } from '@wordpress/i18n';
import { TAB_IDS, selectTab } from '../../../../../utils/tabSelector';
import { payLaterMessaging } from './pay-later-messaging';

export const getFeatures = ( setActiveModal ) => {
	const storeCountry = ppcpSettings?.storeCountry;
	const features = [
		{
			id: 'save_paypal_and_venmo',
			title: __( 'Save PayPal and Venmo', 'woocommerce-paypal-payments' ),
			description: __(
				'Securely save PayPal and Venmo payment methods for subscriptions or return buyers.',
				'woocommerce-paypal-payments'
			),
			buttons: [
				{
					type: 'secondary',
					text: __( 'Configure', 'woocommerce-paypal-payments' ),
					onClick: () => {
						selectTab(
							TAB_IDS.SETTINGS,
							'ppcp--save-payment-methods'
						);
					},
					showWhen: 'enabled',
					class: 'small-button',
				},
				{
					type: 'secondary',
					text: __( 'Apply', 'woocommerce-paypal-payments' ),
					urls: {
						sandbox:
							'https://www.sandbox.paypal.com/bizsignup/entry?product=ADVANCED_VAULTING',
						live: 'https://www.paypal.com/bizsignup/entry?product=ADVANCED_VAULTING',
					},
					showWhen: 'disabled',
					class: 'small-button',
				},
				{
					type: 'tertiary',
					text: __( 'Learn more', 'woocommerce-paypal-payments' ),
					urls: {
						sandbox: '#',
						live: '#',
					},
					class: 'small-button',
				},
			],
		},
		{
			id: 'advanced_credit_and_debit_cards',
			title: __(
				'Advanced Credit and Debit Cards',
				'woocommerce-paypal-payments'
			),
			description: __(
				'Process major credit and debit cards including Visa, Mastercard, American Express and Discover.',
				'woocommerce-paypal-payments'
			),
			buttons: [
				{
					type: 'secondary',
					text: __( 'Configure', 'woocommerce-paypal-payments' ),
					onClick: () => {
						selectTab(
							TAB_IDS.PAYMENT_METHODS,
							'ppcp-card-payments-card'
						).then( () => {
							setActiveModal( 'ppcp-credit-card-gateway' );
						} );
					},
					showWhen: 'enabled',
					class: 'small-button',
				},
				{
					type: 'secondary',
					text: __( 'Apply', 'woocommerce-paypal-payments' ),
					urls: {
						sandbox:
							'https://www.sandbox.paypal.com/bizsignup/entry?product=ppcp',
						live: 'https://www.paypal.com/bizsignup/entry?product=ppcp',
					},
					showWhen: 'disabled',
					class: 'small-button',
				},
				{
					type: 'tertiary',
					text: __( 'Learn more', 'woocommerce-paypal-payments' ),
					url: 'https://developer.paypal.com/studio/checkout/advanced',
					class: 'small-button',
				},
			],
		},
		{
			id: 'alternative_payment_methods',
			title: __(
				'Alternative Payment Methods',
				'woocommerce-paypal-payments'
			),
			description: __(
				'Offer global, country-specific payment options for your customers.',
				'woocommerce-paypal-payments'
			),
			buttons: [
				{
					type: 'secondary',
					text: __( 'Configure', 'woocommerce-paypal-payments' ),
					onClick: () => {
						selectTab(
							TAB_IDS.PAYMENT_METHODS,
							'ppcp-alternative-payments-card'
						);
					},
					showWhen: 'enabled',
					class: 'small-button',
				},
				{
					type: 'secondary',
					text: __( 'Apply', 'woocommerce-paypal-payments' ),
					url: 'https://developer.paypal.com/docs/checkout/apm/',
					showWhen: 'disabled',
					class: 'small-button',
				},
				{
					type: 'tertiary',
					text: __( 'Learn more', 'woocommerce-paypal-payments' ),
					url: 'https://developer.paypal.com/docs/checkout/apm/',
					class: 'small-button',
				},
			],
		},
		{
			id: 'google_pay',
			title: __( 'Google Pay', 'woocommerce-paypal-payments' ),
			description: __(
				'Let customers pay using their Google Pay wallet.',
				'woocommerce-paypal-payments'
			),
			buttons: [
				{
					type: 'secondary',
					text: __( 'Configure', 'woocommerce-paypal-payments' ),
					onClick: () => {
						selectTab(
							TAB_IDS.PAYMENT_METHODS,
							'ppcp-card-payments-card'
						).then( () => {
							setActiveModal( 'ppcp-googlepay' );
						} );
					},
					showWhen: 'enabled',
					class: 'small-button',
				},
				{
					type: 'secondary',
					text: __( 'Apply', 'woocommerce-paypal-payments' ),
					urls: {
						sandbox:
							'https://www.sandbox.paypal.com/bizsignup/add-product?product=payment_methods&capabilities=GOOGLE_PAY',
						live: 'https://www.paypal.com/bizsignup/add-product?product=payment_methods&capabilities=GOOGLE_PAY',
					},
					showWhen: 'disabled',
					class: 'small-button',
				},
				{
					type: 'tertiary',
					text: __( 'Learn more', 'woocommerce-paypal-payments' ),
					url: 'https://developer.paypal.com/docs/checkout/apm/google-pay/',
					class: 'small-button',
				},
			],
			notes: [
				__(
					'¹PayPal Q2 Earnings-2021.',
					'woocommerce-paypal-payments'
				),
			],
		},
		{
			id: 'apple_pay',
			title: __( 'Apple Pay', 'woocommerce-paypal-payments' ),
			description: __(
				'Let customers pay using their Apple Pay wallet.',
				'woocommerce-paypal-payments'
			),
			buttons: [
				{
					type: 'secondary',
					text: __( 'Configure', 'woocommerce-paypal-payments' ),
					onClick: () => {
						selectTab(
							TAB_IDS.PAYMENT_METHODS,
							'ppcp-card-payments-card'
						).then( () => {
							setActiveModal( 'ppcp-applepay' );
						} );
					},
					showWhen: 'enabled',
					class: 'small-button',
				},
				{
					type: 'secondary',
					text: __(
						'Domain registration',
						'woocommerce-paypal-payments'
					),
					urls: {
						sandbox:
							'https://www.sandbox.paypal.com/uccservicing/apm/applepay',
						live: 'https://www.paypal.com/uccservicing/apm/applepay',
					},
					showWhen: 'enabled',
					class: 'small-button',
				},
				{
					type: 'secondary',
					text: __( 'Apply', 'woocommerce-paypal-payments' ),
					urls: {
						sandbox:
							'https://www.sandbox.paypal.com/bizsignup/add-product?product=payment_methods&capabilities=APPLE_PAY',
						live: 'https://www.paypal.com/bizsignup/add-product?product=payment_methods&capabilities=APPLE_PAY',
					},
					showWhen: 'disabled',
					class: 'small-button',
				},
				{
					type: 'tertiary',
					text: __( 'Learn more', 'woocommerce-paypal-payments' ),
					url: 'https://developer.paypal.com/docs/checkout/apm/apple-pay/',
					class: 'small-button',
				},
			],
		},
	];

	const countryData = payLaterMessaging[ storeCountry ] || {};

	if (
		!! window.ppcpSettings?.isPayLaterConfiguratorAvailable &&
		countryData
	) {
		features.push( {
			id: 'pay_later_messaging',
			title: __( 'Pay Later Messaging', 'woocommerce-paypal-payments' ),
			description: __(
				'Let customers know they can buy now and pay later with PayPal. Adding this messaging can boost conversion rates and increase cart sizes by 39%¹, with no extra cost to you—plus, you get paid up front.',
				'woocommerce-paypal-payments'
			),
			buttons: [
				{
					type: 'secondary',
					text: __( 'Configure', 'woocommerce-paypal-payments' ),
					onClick: () => {
						selectTab( TAB_IDS.PAY_LATER_MESSAGING );
					},
					showWhen: 'enabled',
					class: 'small-button',
				},
				{
					type: 'secondary',
					text: __( 'Apply', 'woocommerce-paypal-payments' ),
					urls: {
						sandbox: '#',
						live: '#',
					},
					showWhen: 'disabled',
					class: 'small-button',
				},
				{
					type: 'tertiary',
					text: __( 'Learn more', 'woocommerce-paypal-payments' ),
					url: 'https://developer.paypal.com/studio/checkout/pay-later/us',
					class: 'small-button',
				},
			],
		} );
	}

	return features;
};
