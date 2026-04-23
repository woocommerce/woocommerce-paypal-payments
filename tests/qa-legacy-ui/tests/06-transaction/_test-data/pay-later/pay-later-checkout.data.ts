/**
 * Internal dependencies
 */
import { payLater, orders, customers } from '../../../../resources';

export const payLaterCheckout = [
	{
		title: 'PCP-2864 | Transaction - Checkout - Pay Later - Default order @Critical',
		payment: payLater,
		...orders.default,
	},
	{
		title: 'PCP-1650 | Transaction - Checkout - Pay Later - Order by customer @Critical',
		payment: payLater,
		...orders.byCustomer,
		customer: customers.usa,
	},
	// {
	// 	title: 'PCP-1651 | Transaction - Checkout - Pay Later - Order with free shipping',
	// 	payment: payLater,
	// 	...orders.freeShipping,
	// },
	// {
	// 	title: 'PCP-1654 | Transaction - Checkout - Pay Later - Order with fixed coupon',
	// 	payment: payLater,
	// 	...orders.fixedCoupon10,
	// },
	// {
	// 	title: 'PCP-2865 | Transaction - Checkout - Pay Later - Order with percentage coupon',
	// 	payment: payLater,
	// 	...orders.percentCoupon10,
	// },
	// {
	// 	title: 'PCP-2866 | Transaction - Checkout - Pay Later - Order including free product',
	// 	payment: payLater,
	// 	...orders.includingFreeProduct,
	// },
	// {
	// 	title: 'PCP-2867 | Transaction - Checkout - Pay Later - Order with product without image',
	// 	payment: payLater,
	// 	...orders.productWithoutImage,
	// },
];

export const payLaterCheckoutExcludingTax = [
	{
		title: 'PCP-1656 | Transaction - Checkout - Pay Later - Order with price excluding tax',
		payment: payLater,
		...orders.excludingTax,
	},
];

export const payLaterCheckoutIntentAuthorized = [
	{
		title: 'PCP-2869 | Transaction - Checkout - Pay Later - Order with Intent Authorized',
		payment: {
			...payLater,
			isAuthorized: true,
		},
		...orders.default,
		orderStatus: 'on-hold',
	},
];
