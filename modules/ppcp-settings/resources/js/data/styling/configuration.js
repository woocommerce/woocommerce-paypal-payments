/**
 * Configuration for UI components.
 *
 * @file
 */

import { __ } from '@wordpress/i18n';

export const STYLING_LOCATIONS = {
	cart: {
		value: 'cart',
		label: __( 'Cart', 'woocommerce-paypal-payments' ),
		link: 'https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-cart',
		props: { layout: false, tagline: false },
	},
	classicCheckout: {
		value: 'classicCheckout',
		label: __( 'Classic Checkout', 'woocommerce-paypal-payments' ),
		link: 'https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-checkout',
		props: { layout: true, tagline: true },
	},
	expressCheckout: {
		value: 'expressCheckout',
		label: __( 'Express Checkout', 'woocommerce-paypal-payments' ),
		link: 'https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-block-express-checkout',
		props: { layout: false, tagline: false },
	},
	miniCart: {
		value: 'miniCart',
		label: __( 'Mini Cart', 'woocommerce-paypel-payements' ),
		link: 'https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-mini-cart',
		props: { layout: true, tagline: true },
	},
	product: {
		value: 'product',
		label: __( 'Product Page', 'woocommerce-paypal-payments' ),
		link: 'https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-single-product',
		props: { layout: true, tagline: true },
	},
};

export const STYLING_LABELS = {
	paypal: {
		value: 'paypal',
		label: __( 'PayPal', 'woocommerce-paypal-payments' ),
	},
	checkout: {
		value: 'checkout',
		label: __( 'Checkout', 'woocommerce-paypal-payments' ),
	},
	buynow: {
		value: 'buynow',
		label: __( 'PayPal Buy Now', 'woocommerce-paypal-payments' ),
	},
	pay: {
		value: 'pay',
		label: __( 'Pay with PayPal', 'woocommerce-paypal-payments' ),
	},
};

export const STYLING_COLORS = {
	gold: {
		value: 'gold',
		label: __( 'Gold (Recommended)', 'woocommerce-paypal-payments' ),
	},
	blue: {
		value: 'blue',
		label: __( 'Blue', 'woocommerce-paypal-payments' ),
	},
	silver: {
		value: 'silver',
		label: __( 'Silver', 'woocommerce-paypal-payments' ),
	},
	black: {
		value: 'black',
		label: __( 'Black', 'woocommerce-paypal-payments' ),
	},
	white: {
		value: 'white',
		label: __( 'White', 'woocommerce-paypal-payments' ),
	},
};

export const STYLING_LAYOUTS = {
	vertical: {
		value: 'vertical',
		label: __( 'Vertical', 'woocommerce-paypal-payments' ),
	},
	horizontal: {
		value: 'horizontal',
		label: __( 'Horizontal', 'woocommerce-paypal-payments' ),
	},
};

export const STYLING_SHAPES = {
	rect: {
		value: 'rect',
		label: __( 'Rectangle', 'woocommerce-paypal-payments' ),
	},
	pill: {
		value: 'pill',
		label: __( 'Pill', 'woocommerce-paypal-payments' ),
	},
};

export const STYLING_PAYMENT_METHODS = {
	paypal: {
		value: '',
		label: __( 'PayPal', 'woocommerce-paypal-payments' ),
		checked: true,
		disabled: true,
	},
	venmo: {
		value: 'venmo',
		label: __( 'Venmo', 'woocommerce-paypal-payments' ),
		isFunding: true,
	},
	paylater: {
		value: 'paylater',
		label: __( 'Pay Later', 'woocommerce-paypal-payments' ),
		isFunding: true,
	},
	googlepay: {
		value: 'googlepay',
		label: __( 'Google Pay', 'woocommerce-paypal-payments' ),
	},
	applepay: {
		value: 'applepay',
		label: __( 'Apple Pay', 'woocommerce-paypal-payments' ),
	},
};
