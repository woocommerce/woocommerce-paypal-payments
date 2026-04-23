/**
 * Internal dependencies
 */
import { payPal, orders } from '../../../../resources';

export const payPalClassicCart = [
	{
		title: 'PCP-2870 | Transaction - Classic cart - PayPal - Default order @Critical',
		payment: payPal,
		...orders.default,
	},
	{
		title: 'PCP-2873 | Transaction - Classic cart - PayPal - Order by customer',
		payment: payPal,
		...orders.byCustomer,
	},
	// {
	// 	title: 'PCP-1185 | Transaction - Classic cart - PayPal - Order with free shipping @Critical',
	// 	payment: payPal,
	// 	...orders.freeShipping,
	// },
	// {
	// 	title: 'PCP-1174 | Transaction - Classic cart - PayPal - Order with fixed coupon @Critical',
	// 	payment: payPal,
	// 	...orders.fixedCoupon10,
	// },
	// {
	// 	title: 'PCP-1181 | Transaction - Classic cart - PayPal - Order with percentage coupon @Critical',
	// 	payment: payPal,
	// 	...orders.percentCoupon10,
	// },
	// {
	// 	title: 'PCP-2871 | Transaction - Classic cart - PayPal - Order including free product',
	// 	payment: payPal,
	// 	...orders.includingFreeProduct,
	// },
	// {
	// 	title: 'PCP-2872 | Transaction - Classic cart - PayPal - Order with product without image',
	// 	payment: payPal,
	// 	...orders.productWithoutImage,
	// },
	{
		title: 'PCP-1176 | Transaction - Classic cart - PayPal - Fixed coupon with excluded product is valid for other products @Critical',
		payment: payPal,
		...orders.fixedCoupon10ExcludedProduct,
	},
	{
		title: 'PCP-1183 | Transaction - Classic cart - PayPal - Percentage coupon with excluded product is valid for other products @Critical',
		payment: payPal,
		...orders.percentCoupon10ExcludedProduct,
	},
];

export const payPalClassicCartExcludingTax = [
	{
		title: 'PCP-1190 | Transaction - Classic cart - PayPal - Order with price excluding tax @Critical',
		payment: payPal,
		...orders.excludingTax,
	},
];

export const payPalClassicCartIntentAuthorized = [
	{
		title: 'PCP-1041 | Transaction - Classic cart - PayPal - Order with Intent Authorized @Critical',
		payment: {
			...payPal,
			isAuthorized: true,
		},
		...orders.default,
		orderStatus: 'on-hold',
	},
];

export const payPalClassicCartHorizontalButton = [
	{
		title: 'PCP-2884 | Transaction - Classic cart - PayPal - Horizontal button layout',
		payment: payPal,
		...orders.default,
	},
];
