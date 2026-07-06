/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { payLater } = payments;

export const payLaterPayByLink: ShopOrder[] = [
	{
		title: 'PCP-6492 | Transaction - Pay by link - Pay Later - Customer - Order with negative fee',
		...orders.negative12Fee,
		payment: payLater,
		customer,
	},
	{
		title: 'PCP-6493 | Transaction - Pay by link - Pay Later - Guest - Order with negative fee',
		...orders.negative12Fee,
		payment: payLater,
		customer: guest,
	},
];
