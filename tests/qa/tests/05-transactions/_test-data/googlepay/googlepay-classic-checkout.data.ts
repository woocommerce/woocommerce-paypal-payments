/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

export const googlePayClassicCheckout: ShopOrder[] = [
	{
		title: 'PCP-2969 | Transaction - Classic checkout - Google Pay - Order by customer @Critical',
		...orders.default,
		payment: payments.googlePay,
		customer,
	},
];

export const googlePayClassicCheckoutNegativeFee: ShopOrder[] = [
	{
		title: 'PCP-6610 | Transaction - Classic checkout - Google Pay - Customer - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payments.googlePay,
		customer,
	},
	{
		title: 'PCP-6611 | Transaction - Classic checkout - Google Pay - Guest - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payments.googlePay,
		customer: guest,
	},
];
