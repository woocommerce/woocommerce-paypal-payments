/**
 * Internal dependencies
 */
import { payPal, orders, PcpMerchant } from '../../../../resources';

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

export const specificMerchant: PcpMerchant = {
	email: 'sb-zkep4329130907@business.example.com',
	password: '8z^QK{k_',
	client_id:
		'BAAL_uqnIg5303RyVWgr6v8-LH8r-jBHl1XYJBSB7gAfz_2lzedc1dqaDRHvdqfPFnFU1vYX6UQynK2PHU',
	client_secret:
		'EI5q8VPWzZ4pwdOIj2o25ih3p-htMBF1srqU7i3NM-5jUwZ79davNXnbS60IMCfAThMYTrCAnBA2sTDp',
	account_id: 'MPWPG7V6XSB84',
};

export const payPalClassicCheckoutSpecificMerchant = [
	{
		title: 'PCP-2608 | Transaction - Classic checkout - PayPal - Merchant without reference transaction',
		...orders.default,
		payment: payPal,
		merchant: specificMerchant,
	},
];
