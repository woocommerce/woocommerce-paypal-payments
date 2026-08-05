/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { payLater } = payments;

export const payLaterCheckout: ShopOrder[] = [
	{
		title: 'PCP-2864 | Transaction - Checkout - Pay Later - Default order @Critical',
		payment: payLater,
		...orders.default,
	},
	{
		title: 'PCP-1650 | Transaction - Checkout - Pay Later - Order by customer',
		payment: payLater,
		...orders.byCustomer,
	},
];

export const payLaterCheckoutExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-1656 | Transaction - Checkout - Pay Later - Order with price excluding tax',
		payment: payLater,
		...orders.excludingTax,
	},
];

export const payLaterCheckoutIntentAuthorized: ShopOrder[] = [
	{
		title: 'PCP-2869 | Transaction - Checkout - Pay Later - Order with Intent Authorized',
		payment: {
			...payLater,
			isAuthorized: true,
		},
		...orders.default,
		orderStatus: 'on-hold',
	},
];

export const payLaterCheckoutNegativeFee: ShopOrder[] = [
	{
		title: 'PCP-6571 | Transaction - Checkout - Pay Later - Customer - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payLater,
		customer,
	},
	{
		title: 'PCP-6572 | Transaction - Checkout - Pay Later - Guest - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payLater,
		customer: guest,
	},
];
