/**
 * Internal dependencies
 */
import { payLater, orders, customers } from '../../../../resources';

export const payLaterClassicCheckout = [
	{
		title: 'PCP-1172 | Transaction - Classic checkout - Pay Later - Default order @Critical',
		payment: payLater,
		...orders.default,
	},
	{
		title: 'PCP-2731 | Transaction - Classic checkout - Pay Later - Order by customer',
		payment: payLater,
		...orders.byCustomer,
		costomer: customers.usa,
	},
	// {
	// 	title: 'PCP-1270 | Transaction - Classic checkout - Pay Later - Order with free shipping @Critical',
	// 	payment: payLater,
	// 	...orders.freeShipping,
	// },
	// {
	// 	title: 'PCP-1272 | Transaction - Classic checkout - Pay Later - Order with fixed coupon',
	// 	payment: payLater,
	// 	...orders.fixedCoupon10,
	// },
	// {
	// 	title: 'PCP-2760 | Transaction - Classic checkout - Pay Later - Order with percentage coupon',
	// 	payment: payLater,
	// 	...orders.percentCoupon10,
	// },
	// {
	// 	title: 'PCP-2729 | Transaction - Classic checkout - Pay Later - Order including free product',
	// 	payment: payLater,
	// 	...orders.includingFreeProduct,
	// },
	// {
	// 	title: 'PCP-2730 | Transaction - Classic checkout - Pay Later - Order with product without image',
	// 	payment: payLater,
	// 	...orders.productWithoutImage,
	// },
	// {
	// 	title: 'PCP-3040 | Transaction - Classic checkout - Pay Later- Order with multiple products',
	// 	payment: payLater,
	// 	...orders.multipleProducts,
	// },
];

export const payLaterClassicCheckoutExcludingTax = [
	{
		title: 'PCP-1278 | Transaction - Classic checkout - Pay Later - Order with price excluding tax',
		payment: payLater,
		...orders.excludingTax,
	},
];

export const payLaterClassicCheckoutIntentAuthorized = [
	{
		title: 'PCP-2757 | Transaction - Classic checkout - Pay Later - Order with Intent Authorized',
		...orders.default,
		payment: {
			...payLater,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
	},
];

export const payLaterClassicCheckoutHorizontalButton = [
	{
		title: 'PCP-1200 | Transaction - Classic checkout - Pay Later - Horizontal button layout @Critical',
		...orders.default,
		payment: payLater,
	},
];
