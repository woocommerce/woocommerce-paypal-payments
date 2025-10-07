/**
 * Internal dependencies
 */
import { payLater, orders, customers } from '../../../../resources';

export const payLaterClassicCart = [
	{
		title: 'PCP-2874 | Transaction - Classic cart - Pay Later - Default order @Critical',
		payment: payLater,
		...orders.default,
	},
	{
		title: 'PCP-2881 | Transaction - Classic cart - Pay Later - Order by customer',
		payment: payLater,
		...orders.byCustomer,
		customer: customers.usa,
	},
	// {
	// 	title: 'PCP-2875 | Transaction - Classic cart - Pay Later - Order with free shipping',
	// 	payment: payLater,
	// 	...orders.freeShipping,
	// },
	// {
	// 	title: 'PCP-2876 | Transaction - Classic cart - Pay Later - Order with fixed coupon',
	// 	payment: payLater,
	// 	...orders.fixedCoupon10,
	// },
	// {
	// 	title: 'PCP-2877 | Transaction - Classic cart - Pay Later - Order with percentage coupon',
	// 	payment: payLater,
	// 	...orders.percentCoupon10,
	// },
	// {
	// 	title: 'PCP-2879 | Transaction - Classic cart - Pay Later - Order including free product',
	// 	payment: payLater,
	// 	...orders.includingFreeProduct,
	// },
	// {
	// 	title: 'PCP-2880 | Transaction - Classic cart - Pay Later - Order with product without image',
	// 	payment: payLater,
	// 	...orders.productWithoutImage,
	// },
];

export const payLaterClassicCartExcludingTax = [
	{
		title: 'PCP-2878 | Transaction - Classic cart - Pay Later - Order with price excluding tax @Critical',
		payment: payLater,
		...orders.excludingTax,
	},
];

export const payLaterClassicCartIntentAuthorized = [
	{
		title: 'PCP-2883 | Transaction - Classic cart - Pay Later - Order with Intent Authorized',
		payment: {
			...payLater,
			isAuthorized: true,
		},
		...orders.default,
		orderStatus: 'on-hold',
	},
];

export const payLaterClassicCartHorizontalButton = [
	{
		title: 'PCP-2885 | Transaction - Classic cart - Pay Later - Horizontal button layout',
		payment: payLater,
		...orders.default,
	},
];
