/**
 * Internal dependencies
 */
import { payPal, orders, merchants } from '../../../../resources';

export const payPalClassicCheckout = [
	{
		title: 'PCP-1173 | Transaction - Classic checkout - PayPal - Default order @Critical',
		payment: payPal,
		...orders.default,
	},
	{
		title: 'PCP-1268 | Transaction - Classic checkout - PayPal - Order by customer',
		payment: payPal,
		...orders.byCustomer,
	},
	// {
	// 	title: 'PCP-1260 | Transaction - Classic checkout - PayPal - Order with free shipping @Critical',
	// 	payment: payPal,
	// 	...orders.freeShipping,
	// },
	// {
	// 	title: 'PCP-1262 | Transaction - Classic checkout - PayPal - Order with fixed coupon',
	// 	payment: payPal,
	// 	...orders.fixedCoupon10,
	// },
	// {
	// 	title: 'PCP-1261 | Transaction - Classic checkout - PayPal - Order with percentage coupon @Critical',
	// 	payment: payPal,
	// 	...orders.percentCoupon10,
	// },
	// {
	// 	title: 'PCP-1765 | Transaction - Classic checkout - PayPal - Order including free product @Critical',
	// 	payment: payPal,
	// 	...orders.includingFreeProduct,
	// },
	// {
	// 	title: 'PCP-2055 | Transaction - Classic checkout - PayPal - Order with product without image @Critical',
	// 	payment: payPal,
	// 	...orders.productWithoutImage,
	// },
	// {
	// 	title: 'PCP-3033 | Transaction - Classic checkout - PayPal - Order with multiple products',
	// 	payment: payPal,
	// 	...orders.multipleProducts,
	// },
];

export const payPalClassicCheckoutExcludingTax = [
	{
		title: 'PCP-1269 | Transaction - Classic checkout - PayPal - Order with price excluding tax',
		payment: payPal,
		...orders.excludingTax,
	},
];

export const payPalClassicCheckoutIntentAuthorized = [
	{
		title: 'PCP-2756 | Transaction - Classic checkout - PayPal - Order with Intent Authorized',
		...orders.default,
		payment: {
			...payPal,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
	},
];

export const payPalClassicCheckoutHorizontalButton = [
	{
		title: 'PCP-1198 | Transaction - Classic checkout - PayPal - Horizontal button layout @Critical',
		...orders.default,
		payment: payPal,
	},
];

export const payPalClassicCheckoutSpecificMerchant = [
	{
		title: 'PCP-2608 | Transaction - Classic checkout - PayPal - Merchant without reference transaction',
		...orders.default,
		payment: payPal,
		merchant: merchants.noReferenceTransaction,
	},
];
