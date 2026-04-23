/**
 * Internal dependencies
 */
import { payPal, orders } from '../../../../resources';

export const payPalCheckout = [
	{
		title: 'PCP-1641 | Transaction - Checkout - PayPal - Default order @Critical',
		payment: payPal,
		...orders.default,
	},
	{
		title: 'PCP-1643 | Transaction - Checkout - PayPal - Order by customer @Critical',
		payment: payPal,
		...orders.byCustomer,
	},
	// {
	// 	title: 'PCP-1645 | Transaction - Checkout - PayPal - Order with free shipping',
	// 	payment: payPal,
	// 	...orders.freeShipping,
	// },
	// {
	// 	title: 'PCP-1647 | Transaction - Checkout - PayPal - Order with fixed coupon',
	// 	payment: payPal,
	// 	...orders.fixedCoupon10,
	// },
	// {
	// 	title: 'PCP-2861 | Transaction - Checkout - PayPal - Order with percentage coupon',
	// 	payment: payPal,
	// 	...orders.percentCoupon10,
	// },
	// {
	// 	title: 'PCP-2862 | Transaction - Checkout - PayPal - Order including free product',
	// 	payment: payPal,
	// 	...orders.includingFreeProduct,
	// },
	// {
	// 	title: 'PCP-2863 | Transaction - Checkout - PayPal - Order with product without image',
	// 	payment: payPal,
	// 	...orders.productWithoutImage,
	// },
];

export const payPalCheckoutExcludingTax = [
	{
		title: 'PCP-1649 | Transaction - Checkout - PayPal - Order with price excluding tax',
		payment: payPal,
		...orders.excludingTax,
	},
];

export const payPalCheckoutIntentAuthorized = [
	{
		title: 'PCP-2868 | Transaction - Checkout - PayPal - Order with Intent Authorized',
		payment: {
			...payPal,
			isAuthorized: true,
		},
		...orders.default,
		orderStatus: 'on-hold',
	},
];
