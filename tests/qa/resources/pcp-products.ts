/**
 * External dependencies
 */
import { products as baseProducts } from '@inpsyde/playwright-utils/build';

const subscriptionFreeTrial: WooCommerce.CreateProduct = {
	name: 'Subscription Free Trial Test Product',
	slug: 'subscription-free-trial-test-product',
	type: 'subscription',
	regular_price: '100.00',
	description:
		'Subscribe to our Free Trial magazine for expert advice, tips, and insights on growing the best clothes year-round.',
	short_description: 'Monthly Free Trial magazine on apple growing and orchard tips.',
	meta_data: [
		{ key: '_subscription_price', value: '100.00' },
		{ key: '_subscription_period', value: 'month' },
		{ key: '_subscription_period_interval', value: '1' },
		{ key: '_subscription_length', value: '0' },
		{ key: '_subscription_trial_length', value: '15' },
		{ key: '_subscription_trial_period', value: 'day' },
		
	],
	images: [
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/album-1.jpg',
		},
	],
};

const subscriptionPayPal: WooCommerce.CreateProduct = {
	name: 'PayPal Subscription Test Product',
	slug: 'paypal-subscription-test-product',
	type: 'subscription',
	regular_price: '100.00',
	description:
		'Subscribe to our PayPal plugin magazine for expert advice, tips, and insights on growing the best clothes year-round.',
	short_description: 'Monthly PayPal plugin magazine on apple growing and orchard tips.',
	meta_data: [
		{ key: '_subscription_price', value: '100.00' },
		{ key: '_subscription_period', value: 'month' },
		{ key: '_subscription_period_interval', value: '1' },
		{ key: '_subscription_length', value: '0' },
		{ key: '_ppcp_enable_subscription_product', value: 'yes' },
		{ key: '_ppcp_subscription_plan_name', value: 'test' },
		
	],
	images: [
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/album-1.jpg',
		},
	],
};

const subscriptionPayPalFreeTrial: WooCommerce.CreateProduct = {
	name: 'PayPal Subscription Free Trial Test Product',
	slug: 'paypal-subscription-free-trial-test-product',
	type: 'subscription',
	regular_price: '100.00',
	description:
		'Subscribe to our Free Trial PayPal plugin magazine for expert advice, tips, and insights on growing the best clothes year-round.',
	short_description: 'Monthly Free Trial PayPal plugin magazine on apple growing and orchard tips.',
	meta_data: [
		{ key: '_subscription_price', value: '100.00' },
		{ key: '_subscription_period', value: 'month' },
		{ key: '_subscription_period_interval', value: '1' },
		{ key: '_subscription_length', value: '0' },
		{ key: '_subscription_trial_length', value: '15' },
		{ key: '_subscription_trial_period', value: 'day' },
		{ key: '_ppcp_enable_subscription_product', value: 'yes' },
		{ key: '_ppcp_subscription_plan_name', value: 'test' },
		
	],
	images: [
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/album-1.jpg',
		},
	],
};

export const products: {
	[ key: string ]: WooCommerce.CreateProduct;
} = {
	...baseProducts,
	subscriptionFreeTrial,
	subscriptionPayPal,
	subscriptionPayPalFreeTrial,
};
