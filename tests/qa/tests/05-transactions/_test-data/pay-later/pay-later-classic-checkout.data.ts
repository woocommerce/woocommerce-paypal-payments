/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { payLater } = payments;

export const payLaterClassicCheckout: ShopOrder[] = [
	{
		title: 'PCP-1172 | Transaction - Classic checkout - Pay Later - Default order @Critical',
		payment: payLater,
		...orders.default,
	},
	{
		title: 'PCP-2731 | Transaction - Classic checkout - Pay Later - Order by customer',
		payment: payLater,
		...orders.byCustomer,
	},
];

export const payLaterClassicCheckoutExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-1278 | Transaction - Classic checkout - Pay Later - Order with price excluding tax',
		payment: payLater,
		...orders.excludingTax,
	},
];

export const payLaterClassicCheckoutIntentAuthorized: ShopOrder[] = [
	{
		title: 'PCP-2757 | Transaction - Classic checkout - Pay Later - Order with Intent Authorized',
		...orders.default,
		payment: {
			...payLater,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
	},
];

export const payLaterClassicCheckoutHorizontalButton: ShopOrder[] = [
	{
		title: 'PCP-1200 | Transaction - Classic checkout - Pay Later - Horizontal button layout @Critical',
		...orders.default,
		payment: payLater,
	},
];

export const payLaterClassicCheckoutNegativeFee: ShopOrder[] = [
	{
		title: 'PCP-6606 | Transaction - Classic checkout - Pay Later - Customer - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payLater,
		customer,
	},
	{
		title: 'PCP-6607 | Transaction - Classic checkout - Pay Later - Guest - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payLater,
		customer: guest,
	},
];
