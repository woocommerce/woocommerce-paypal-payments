/**
 * Internal dependencies
 */
import { acdc, acdc3ds, orders } from '../../../../resources';

export const acdcClassicCheckout = [
	{
		title: 'PCP-1202 | Transaction - Classic checkout - ACDC - Default order @Critical',
		payment: acdc,
		...orders.default,
	},
	{
		title: 'PCP-2743 | Transaction - Classic checkout - ACDC - Order by customer',
		payment: acdc,
		...orders.byCustomer,
	},
	// {
	// 	title: 'PCP-1210 | Transaction - Classic checkout - ACDC - Order with free shipping',
	// 	payment: acdc,
	// 	...orders.freeShipping,
	// },
	// {
	// 	title: 'PCP-1255 | Transaction - Classic checkout - ACDC - Order with fixed coupon',
	// 	payment: acdc,
	// 	...orders.fixedCoupon10,
	// },
	// {
	// 	title: 'PCP-2740 | Transaction - Classic checkout - ACDC - Order with percentage coupon',
	// 	payment: acdc,
	// 	...orders.percentCoupon10,
	// },
	// {
	// 	title: 'PCP-2741 | Transaction - Classic checkout - ACDC - Order including free product',
	// 	payment: acdc,
	// 	...orders.includingFreeProduct,
	// },
	// {
	// 	title: 'PCP-2742 | Transaction - Classic checkout - ACDC - Order with product without image',
	// 	payment: acdc,
	// 	...orders.productWithoutImage,
	// },
	// {
	// 	title: 'PCP-3048 | Transaction - Classic checkout - ACDC - Order with multiple products',
	// 	payment: acdc,
	// 	...orders.multipleProducts,
	// },
];

export const acdcClassicCheckout3ds = [
	{
		title: 'PCP-1135 | Transaction - Classic checkout - ACDC - Contingency for 3D Secure = Always trigger 3D secure',
		...orders.default,
		payment: acdc3ds,
	},
	{
		title: 'PCP-1209 | Transaction - Classic checkout - ACDC - Order paid with card requiring 3DS',
		...orders.byCustomer,
		payment: acdc3ds,
	},
];

export const acdcClassicCheckoutExcludingTax = [
	{
		title: 'PCP-1259 | Transaction - Classic checkout - ACDC - Order with price excluding tax',
		payment: acdc,
		...orders.excludingTax,
	},
];

export const acdcClassicCheckoutDebugging = [
	{
		title: 'PCP-2478 | Transaction - Classic checkout - ACDC - Order with new credit card and debugging enabled',
		...orders.default,
		payment: {
			...acdc,
			threeDSTransaction: true,
		},
	},
];
