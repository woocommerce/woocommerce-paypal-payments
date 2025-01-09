import { __, sprintf } from '@wordpress/i18n';

const createStandardFields = ( methodId, defaultTitle ) => ( {
	checkoutPageTitle: {
		type: 'text',
		default: defaultTitle,
		label: __( 'Checkout page title', 'woocommerce-paypal-payments' ),
	},
	checkoutPageDescription: {
		type: 'text',
		default: sprintf(
			/* translators: %s: payment method title */
			__( 'Pay with %s', 'woocommerce-paypal-payments' ),
			defaultTitle
		),
		label: __( 'Checkout page description', 'woocommerce-paypal-payments' ),
	},
} );

const paymentMethods = {
	// PayPal Checkout methods
	paypal: {
		fields: {
			...createStandardFields( 'paypal', 'PayPal' ),
			showLogo: {
				type: 'toggle',
				default: false,
				label: __( 'Show logo', 'woocommerce-paypal-payments' ),
			},
		},
	},
	venmo: {
		fields: createStandardFields( 'venmo', 'Venmo' ),
	},
	paypal_credit: {
		fields: createStandardFields( 'paypal_credit', 'PayPal Credit' ),
	},
	credit_and_debit_card_payments: {
		fields: createStandardFields(
			'credit_and_debit_card_payments',
			__(
				'Credit and debit card payments',
				'woocommerce-paypal-payments'
			)
		),
	},

	// Online Card Payments
	advanced_credit_and_debit_card_payments: {
		fields: {
			...createStandardFields(
				'advanced_credit_and_debit_card_payments',
				__(
					'Advanced Credit and Debit Card Payments',
					'woocommerce-paypal-payments'
				)
			),
			threeDSecure: {
				type: 'radio',
				default: 'no-3d-secure',
				label: __( '3D Secure', 'woocommerce-paypal-payments' ),
				description: __(
					'Authenticate cardholders through their card issuers to reduce fraud and improve transaction security. Successful 3D Secure authentication can shift liability for fraudulent chargebacks to the card issuer.',
					'woocommerce-paypal-payments'
				),
				options: [
					{
						label: __(
							'No 3D Secure',
							'woocommerce-paypal-payments'
						),
						value: 'no-3d-secure',
					},
					{
						label: __(
							'Only when required',
							'woocommerce-paypal-payments'
						),
						value: 'only-required-3d-secure',
					},
					{
						label: __(
							'Always require 3D Secure',
							'woocommerce-paypal-payments'
						),
						value: 'always-3d-secure',
					},
				],
			},
		},
	},
	fastlane: {
		fields: {
			...createStandardFields( 'fastlane', 'Fastlane by PayPal' ),
			cardholderName: {
				type: 'toggle',
				default: false,
				label: __(
					'Display cardholder name',
					'woocommerce-paypal-payments'
				),
			},
			displayWatermark: {
				type: 'toggle',
				default: false,
				label: __(
					'Display Fastlane Watermark',
					'woocommerce-paypal-payments'
				),
			},
		},
	},

	// Digital Wallets
	apple_pay: {
		fields: createStandardFields( 'apple_pay', 'Apple Pay' ),
	},
	google_pay: {
		fields: createStandardFields( 'google_pay', 'Google Pay' ),
	},

	// Alternative Payment Methods
	bancontact: {
		fields: createStandardFields( 'bancontact', 'Bancontact' ),
	},
	ideal: {
		fields: createStandardFields( 'ideal', 'iDEAL' ),
	},
	eps: {
		fields: createStandardFields( 'eps', 'eps' ),
	},
	blik: {
		fields: createStandardFields( 'blik', 'BLIK' ),
	},
	mybank: {
		fields: createStandardFields( 'mybank', 'MyBank' ),
	},
	przelewy24: {
		fields: createStandardFields( 'przelewy24', 'Przelewy24' ),
	},
	trustly: {
		fields: createStandardFields( 'trustly', 'Trustly' ),
	},
	multibanco: {
		fields: createStandardFields( 'multibanco', 'Multibanco' ),
	},
	pui: {
		fields: createStandardFields( 'pui', 'Pay upon Invoice' ),
	},
	oxxo: {
		fields: createStandardFields( 'oxxo', 'OXXO' ),
	},
};

// Function to get configuration for a payment method
export const getPaymentMethods = ( method ) => {
	if ( ! method?.id ) {
		return null;
	}

	// If method has specific config, return it
	if ( paymentMethods[ method.id ] ) {
		return {
			...paymentMethods[ method.id ],
			icon: method.icon,
		};
	}

	// Return standard config for new payment methods
	return {
		fields: createStandardFields( method.id, method.title ),
		icon: method.icon,
	};
};

// Function to check if a method has settings defined
export const hasSettings = ( methodId ) => {
	return Boolean( methodId && paymentMethods[ methodId ] );
};
