/**
 * Internal dependencies
 */
import { payments, orders } from '../../../../resources';

const { payPal } = payments;

export const payPalCheckout = [
	{
		title: 'PCP-1641 | Transaction - Checkout - PayPal - Default order @Critical',
		payment: payPal,
		...orders.default,
	},
	{
		title: 'PCP-1643 | Transaction - Checkout - PayPal - Order by customer @Critical',
		payment: payPal,
		...orders.byCustomer,
	},
];

export const payPalCheckoutExcludingTax = [
	{
		title: 'PCP-1649 | Transaction - Checkout - PayPal - Order with price excluding tax',
		payment: payPal,
		...orders.excludingTax,
	},
];

export const payPalCheckoutIntentAuthorized = [
	{
		title: 'PCP-2868 | Transaction - Checkout - PayPal - Order with Intent Authorized',
		payment: {
			...payPal,
			isAuthorized: true,
		},
		...orders.default,
		orderStatus: 'on-hold',
	},
];
