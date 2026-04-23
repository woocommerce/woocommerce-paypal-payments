/**
 * Internal dependencies
 */
import { payLater, orders, customers } from '../../../../resources';

export const payLaterCart = [
	{
		title: 'PCP-1625 | Transaction - Cart - Pay Later - Default order @Critical',
		payment: payLater,
		...orders.default,
	},
	{
		title: 'PCP-2858 | Transaction - Cart - Pay Later - Order by customer',
		payment: payLater,
		...orders.byCustomer,
		costomer: customers.usa,
	},
	// {
	// 	title: 'PCP-2852 | Transaction - Cart - Pay Later - Order with free shipping',
	// 	payment: payLater,
	// 	...orders.freeShipping,
	// },
	// {
	// 	title: 'PCP-2853 | Transaction - Cart - Pay Later - Order with fixed coupon',
	// 	payment: payLater,
	// 	...orders.fixedCoupon10,
	// },
	// {
	// 	title: 'PCP-2854 | Transaction - Cart - Pay Later - Order with percentage coupon',
	// 	payment: payLater,
	// 	...orders.percentCoupon10,
	// },
	// {
	// 	title: 'PCP-2856 | Transaction - Cart - Pay Later - Order including free product',
	// 	payment: payLater,
	// 	...orders.includingFreeProduct,
	// },
	// {
	// 	title: 'PCP-2857 | Transaction - Cart - Pay Later - Order with product without image',
	// 	payment: payLater,
	// 	...orders.productWithoutImage,
	// },
];

export const payLaterCartExcludingTax = [
	{
		title: 'PCP-2855 | Transaction - Cart - Pay Later - Order with price excluding tax',
		payment: payLater,
		...orders.excludingTax,
	},
];

export const payLaterCartIntentAuthorized = [
	{
		title: 'PCP-2860 | Transaction - Cart - Pay Later - Order with Intent Authorized',
		payment: {
			...payLater,
			isAuthorized: true,
		},
		...orders.default,
		orderStatus: 'on-hold',
	},
];
