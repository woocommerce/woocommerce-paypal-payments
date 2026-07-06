/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { payPal } = payments;

export const payPalPayByLink: ShopOrder[] = [
	{
		title: 'PCP-2886 | Transaction - Pay by link - PayPal - Customer - Default order @Critical',
		...orders.byCustomer,
		payment: payPal,
		customer,
	},
	{
		title: 'PCP-2887 | Transaction - Pay by link - PayPal - Guest - Default order @Critical',
		...orders.default,
		payment: payPal,
		customer: guest,
	},
	{
		title: 'PCP-6460 | Transaction - Pay by link - PayPal - Customer - Order with negative fee',
		...orders.negative12Fee,
		payment: payPal,
		customer,
	},
	{
		title: 'PCP-6485 | Transaction - Pay by link - PayPal - Guest - Order with negative fee',
		...orders.negative12Fee,
		payment: payPal,
		customer: guest,
	},
];

export const payPalPayByLinkExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-6495 | Transaction - Pay by link - PayPal - Guest - Order with price excluding tax',
		payment: payPal,
		...orders.excludingTax,
		customer: guest,
	},
];

export const payPalPayByLinkIntentAuthorized: ShopOrder[] = [
	{
		title: 'PCP-3333 | Transaction - Pay by link - PayPal - Order with Intent Authorized',
		payment: {
			...payPal,
			isAuthorized: true,
		},
		...orders.default,
		orderStatus: 'on-hold',
		customer: guest,
	},
];
