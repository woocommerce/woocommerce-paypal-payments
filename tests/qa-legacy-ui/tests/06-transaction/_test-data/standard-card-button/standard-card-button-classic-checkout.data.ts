/**
 * Internal dependencies
 */
import { standardCardButton, orders } from '../../../../resources';

export const standardCardButtonClassicCheckout = [
	{
		title: 'PCP-1211 | Transaction - Classic checkout - Standard Card Button - Default order @Critical',
		payment: standardCardButton,
		...orders.default,
	},
	{
		title: 'PCP-2747 | Transaction - Classic checkout - Standard Card Button - Order by customer',
		payment: standardCardButton,
		...orders.byCustomer,
	},
	// {
	// 	title: 'PCP-1247 | Transaction - Classic checkout - Standard Card Button - Order with free shipping',
	// 	payment: standardCardButton,
	// 	...orders.freeShipping,
	// },
	// {
	// 	title: 'PCP-1249 | Transaction - Classic checkout - Standard Card Button - Order with fixed coupon',
	// 	payment: standardCardButton,
	// 	...orders.fixedCoupon10,
	// },
	// {
	// 	title: 'PCP-2744 | Transaction - Classic checkout - Standard Card Button - Order with percentage coupon',
	// 	payment: standardCardButton,
	// 	...orders.percentCoupon10,
	// },
	// {
	// 	title: 'PCP-2745 | Transaction - Classic checkout - Standard Card Button - Order including free product',
	// 	payment: standardCardButton,
	// 	...orders.includingFreeProduct,
	// },
	// {
	// 	title: 'PCP-2746 | Transaction - Classic checkout - Standard Card Button - Order with product without image',
	// 	payment: standardCardButton,
	// 	...orders.productWithoutImage,
	// },
	// {
	// 	title: 'PCP-3052 | Transaction - Classic checkout - Standard Card Button - Order with multiple products',
	// 	payment: standardCardButton,
	// 	...orders.multipleProducts,
	// },
];

export const standardCardButtonClassicCheckoutExcludingTax = [
	{
		title: 'PCP-1253 | Transaction - Classic checkout - Standard Card Button - Order with price excluding tax',
		payment: standardCardButton,
		...orders.excludingTax,
	},
];

export const standardCardButtonClassicCheckoutIntentAuthorized = [
	{
		title: 'PCP-2759 | Transaction - Classic checkout - Standard Card Button - Order with Intent Authorized',
		...orders.default,
		payment: {
			...standardCardButton,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
	},
];
