/**
 * Internal dependencies
 */
import { payments, orders, Pcp } from '../../../../resources';

const { payPal } = payments;

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
