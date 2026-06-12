/**
 * External dependencies
 */
import { products as baseProducts } from '@inpsyde/playwright-utils/build';

const variationNotSelected100: WooCommerce.CreateProduct = {
	name: 'Variable T-Shirt',
	slug: 'variable-t-shirt',
	type: 'variable',
	regular_price: '100.00',
	description:
		'A comfortable t-shirt available in multiple colors and sizes.',
	short_description: 'Variable t-shirt with color and size options.',
	attributes: [
		{
			name: 'Color',
			variation: true,
			options: [ 'Gray', 'Red' ],
		},
		{
			name: 'Size',
			variation: true,
			options: [ 'M', 'L' ],
		},
	],
	default_attributes: [],
	variations: [
		{
			attributes: [
				{ name: 'Color', option: 'Gray' },
				{ name: 'Size', option: 'M' },
			],
			regular_price: '100.00',
			stock_status: 'instock',
			manage_stock: false,
		},
		{
			attributes: [
				{ name: 'Color', option: 'Gray' },
				{ name: 'Size', option: 'L' },
			],
			regular_price: '100.00',
			stock_status: 'instock',
			manage_stock: false,
		},
		{
			attributes: [
				{ name: 'Color', option: 'Red' },
				{ name: 'Size', option: 'M' },
			],
			regular_price: '100.00',
			stock_status: 'instock',
			manage_stock: false,
		},
		{
			attributes: [
				{ name: 'Color', option: 'Red' },
				{ name: 'Size', option: 'L' },
			],
			regular_price: '100.00',
			stock_status: 'instock',
			manage_stock: false,
		},
	],
	images: [
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/vneck-tee-2.jpg',
		},
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/tshirt-2.jpg',
		},
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/t-shirt-with-logo-1.jpg',
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
	short_description:
		'Monthly PayPal plugin magazine on apple growing and orchard tips.',
	meta_data: [
		{ key: '_subscription_price', value: '100.00' },
		{ key: '_subscription_period', value: 'month' },
		{ key: '_subscription_period_interval', value: '1' },
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
	short_description:
		'Monthly Free Trial PayPal plugin magazine on apple growing and orchard tips.',
	meta_data: [
		{ key: '_subscription_price', value: '100.00' },
		{ key: '_subscription_period', value: 'month' },
		{ key: '_subscription_period_interval', value: '1' },
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

const simpleWithStock: WooCommerce.CreateProduct = {
	name: 'Simple Product With Stock',
	slug: 'simple-product-with-stock',
	type: 'simple',
	regular_price: '100.00',
	description:
		'Our clothes are juicy, sweet, and ideal for a healthy snack or enhancing your favorite recipes.',
	short_description: 'Fresh, crisp clothes perfect for snacks and desserts.',
	images: [
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/long-sleeve-tee-2.jpg',
		},
	],
	stock_status: 'instock',
	manage_stock: true,
	stock_quantity: 1000,
};

export const products: {
	[ key: string ]: WooCommerce.CreateProduct;
} = {
	...baseProducts,
	variationNotSelected100,
	subscriptionPayPal,
	subscriptionPayPalFreeTrial,
	simpleWithStock,
};
