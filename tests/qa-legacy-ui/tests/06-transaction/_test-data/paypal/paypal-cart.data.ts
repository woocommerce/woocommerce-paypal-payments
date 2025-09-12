/**
 * Internal dependencies
 */
import { payPal, orders } from '../../../../resources';

export const payPalCart = [
	{
		title: 'PCP-2841 | Transaction - Cart - PayPal - Default order @Critical',
		...orders.default,
		payment: payPal,
	},
	{
		title: 'PCP-1623 | Transaction - Cart - PayPal - Order by customer',
		...orders.byCustomer,
		payment: payPal,
	},
	// {
	// 	title: 'PCP-1636 | Transaction - Cart - PayPal - Order with free shipping',
	// 	...orders.freeShipping,
	// 	payment: payPal,
	// },
	// {
	// 	title: 'PCP-1627 | Transaction - Cart - PayPal - Order with fixed coupon',
	// 	...orders.fixedCoupon10,
	// 	payment: payPal,
	// },
	// {
	// 	title: 'PCP-1632 | Transaction - Cart - PayPal - Order with percentage coupon',
	// 	...orders.percentCoupon10,
	// 	payment: payPal,
	// },
	// {
	// 	title: 'PCP-2850 | Transaction - Cart - PayPal - Order including free product',
	// 	...orders.includingFreeProduct,
	// 	payment: payPal,
	// },
	// {
	// 	title: 'PCP-2851 | Transaction - Cart - PayPal - Order with product without image',
	// 	...orders.productWithoutImage,
	// 	payment: payPal,
	// },
];

export const payPalCartExcludingTax = [
	{
		title: 'PCP-1639 | Transaction - Cart - PayPal - Order with price excluding tax',
		...orders.excludingTax,
		payment: payPal,
	},
];

export const payPalCartIntentAuthorized = [
	{
		title: 'PCP-2859 | Transaction - Cart - PayPal - Order with Intent Authorized',
		payment: {
			...payPal,
			isAuthorized: true,
		},
		...orders.default,
		orderStatus: 'on-hold',
	},
];
