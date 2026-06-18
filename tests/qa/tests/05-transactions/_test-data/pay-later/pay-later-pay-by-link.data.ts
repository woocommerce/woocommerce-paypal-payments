/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { payLater } = payments;

export const payLaterPayByLink: ShopOrder[] = [
	{
		title: 'PCP-6492 | Transaction - Pay by link - Pay Later - Customer - Order with negative fixed fee',
		...orders.negative15FixedFee,
		payment: payLater,
		customer,
	},
	{
		title: 'PCP-6493 | Transaction - Pay by link - Pay Later - Guest - Order with negative fixed fee',
		...orders.negative15FixedFee,
		payment: payLater,
		customer: guest,
	},
	{
		title: 'PCP-0000 | Transaction - Pay by link - Pay Later - Guest - Order with negative percentage fee',
		...orders.negative12PercentFee,
		payment: payLater,
		customer: guest,
	},
];
