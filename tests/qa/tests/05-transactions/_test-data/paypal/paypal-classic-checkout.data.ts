/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { payPal } = payments;

export const payPalClassicCheckout: ShopOrder[] = [
	{
		title: 'PCP-1173 | Transaction - Classic checkout - PayPal - Default order @Critical @Smoke',
		payment: payPal,
		...orders.default,
	},
	{
		title: 'PCP-1268 | Transaction - Classic checkout - PayPal - Order by customer',
		payment: payPal,
		...orders.byCustomer,
	},
];

export const payPalClassicCheckoutExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-1269 | Transaction - Classic checkout - PayPal - Order with price excluding tax',
		payment: payPal,
		...orders.excludingTax,
	},
];

export const payPalClassicCheckoutIntentAuthorized: ShopOrder[] = [
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

export const payPalClassicCheckoutNegativeFee: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-6604
		title: 'PCP-6604 | Transaction - Classic checkout - PayPal - Customer - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payPal,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6605
		title: 'PCP-6605 | Transaction - Classic checkout - PayPal - Guest - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payPal,
		customer: guest,
	},
];
