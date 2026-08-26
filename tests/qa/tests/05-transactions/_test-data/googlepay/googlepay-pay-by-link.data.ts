/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { googlePay } = payments;

export const googlePayPayByLink: ShopOrder[] = [
	{
		title: 'PCP-6496 | Transaction - Pay by link - Google Pay - Customer - Order with negative fee',
		...orders.negative12Fee,
		payment: googlePay,
		customer,
	},
	{
		title: 'PCP-6497 | Transaction - Pay by link - Google Pay - Guest - Order with negative fee',
		...orders.negative12Fee,
		payment: googlePay,
		customer: guest,
	},
];
