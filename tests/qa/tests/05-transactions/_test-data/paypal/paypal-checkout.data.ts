/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { payPal } = payments;

export const payPalCheckout: ShopOrder[] = [
	{
		title: 'PCP-1641 | Transaction - Checkout - PayPal - Default order @Critical @Smoke',
		...orders.default,
		payment: payPal,
		customer: guest,
	},
	{
		title: 'PCP-1643 | Transaction - Checkout - PayPal - Order by customer',
		...orders.default,
		payment: payPal,
		customer
	},
];

export const payPalCheckoutExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-1649 | Transaction - Checkout - PayPal - Order with price excluding tax',
		...orders.excludingTax,
		payment: payPal,
		customer: guest,
	},
];

export const payPalCheckoutIntentAuthorized: ShopOrder[] = [
	{
		title: 'PCP-2868 | Transaction - Checkout - PayPal - Order with Intent Authorized',
		...orders.default,
		payment: {
			...payPal,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
		customer: guest,
	},
];

export const payPalCheckoutNegativeFee: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-6568
		title: 'PCP-6568 | Transaction - Checkout - PayPal - Customer - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payPal,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6569
		title: 'PCP-6569 | Transaction - Checkout - PayPal - Guest - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payPal,
		customer: guest,
	},
];
