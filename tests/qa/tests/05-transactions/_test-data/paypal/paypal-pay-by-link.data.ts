/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { payPal } = payments;

export const payPalPayByLink: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-2886
		title: 'PCP-2886 | Transaction - Pay by link - PayPal - Customer - Default order @Critical',
		...orders.byCustomer,
		payment: payPal,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-2887
		title: 'PCP-2887 | Transaction - Pay by link - PayPal - Guest - Default order @Critical',
		...orders.default,
		payment: payPal,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6460
		title: 'PCP-6460 | Transaction - Pay by link - PayPal - Customer - Order with negative fixed fee',
		...orders.negative15FixedFee,
		payment: payPal,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6568
		title: 'PCP-6568 | Transaction - Pay by link - PayPal - Customer - Order with negative percentage fee',
		...orders.negative12PercentFee,
		payment: payPal,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6485
		title: 'PCP-6485 | Transaction - Pay by link - PayPal - Guest - Order with negative fixed fee',
		...orders.negative15FixedFee,
		payment: payPal,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6569
		title: 'PCP-6569 | Transaction - Pay by link - PayPal - Guest - Order with negative percentage fee',
		...orders.negative12PercentFee,
		payment: payPal,
		customer: guest,
	},
];

export const payPalPayByLinkExcludingTax: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-6495
		title: 'PCP-6495 | Transaction - Pay by link - PayPal - Guest - Order with price excluding tax',
		payment: payPal,
		...orders.excludingTax,
		customer: guest,
	},
];

export const payPalPayByLinkIntentAuthorized: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-3333
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
