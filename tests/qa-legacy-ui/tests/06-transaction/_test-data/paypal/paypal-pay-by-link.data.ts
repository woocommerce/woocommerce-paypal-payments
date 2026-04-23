/**
 * Internal dependencies
 */
import { customers, guests, payPal, orders } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

export const payPalPayByLink = [
	{
		title: 'PCP-2886 | Transaction - Pay by link - PayPal - Customer - Default order @Critical',
		...orders.default,
		payment: payPal,
		customer,
	},
	{
		title: 'PCP-2887 | Transaction - Pay by link - PayPal - Guest - Default order @Critical',
		...orders.default,
		payment: payPal,
		customer: guest,
	},
	// {
	// 	title: 'PCP-1291 | Transaction - Pay by link - PayPal - Customer - Order with only billing address @Critical',
	// 	...orders.default,
	// 	payment: payPal,
	// 	customer: {
	// 		...customer,
	// 		shipping: undefined,
	// 	},
	// },
	// {
	// 	title: 'PCP-1292 | Transaction - Pay by link - PayPal - Guest - Order with only billing address @Critical',
	// 	...orders.default,
	// 	payment: payPal,
	// 	customer: {
	// 		...guest,
	// 		shipping: undefined,
	// 	},
	// },
	// {
	// 	title: 'PCP-1293 | Transaction - Pay by link - PayPal - Customer - Order with multiple products @Critical',
	// 	...orders.multipleProducts,
	// 	payment: payPal,
	// 	customer,
	// },
	// {
	// 	title: 'PCP-1294 | Transaction - Pay by link - PayPal - Guest - Order with multiple products @Critical',
	// 	...orders.multipleProducts,
	// 	payment: payPal,
	// 	customer: guest,
	// },
	// {
	// 	title: 'PCP-1295 | Transaction - Pay by link - PayPal - Customer - Order with coupon @Critical',
	// 	...orders.fixedCoupon10,
	// 	payment: payPal,
	// 	customer,
	// },
	// {
	// 	title: 'PCP-1296 | Transaction - Pay by link - PayPal - Guest - Order with coupon @Critical',
	// 	...orders.fixedCoupon10,
	// 	payment: payPal,
	// 	customer: guest,
	// },
	// {
	// 	title: 'PCP-1297 | Transaction - Pay by link - PayPal - Customer - Order with fee @Critical',
	// 	...orders.fixedFee10,
	// 	payment: payPal,
	// 	customer,
	// },
	// {
	// 	title: 'PCP-1298 | Transaction - Pay by link - PayPal - Guest - Order with fee @Critical',
	// 	...orders.fixedFee10,
	// 	payment: payPal,
	// 	customer: guest,
	// },
];

export const payPalPayByLinkDebugging = [
	// {
	// 	title: 'PCP-2061 | Transaction - Pay by link - PayPal - Order with enabled WP Debugging plugin @Critical',
	// 	...orders.default,
	// 	payment: payPal,
	// 	customer: guest,
	// },
];
