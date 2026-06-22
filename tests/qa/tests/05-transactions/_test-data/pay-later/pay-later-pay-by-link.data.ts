/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { payLater } = payments;

export const payLaterPayByLink: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-6492
		title: 'PCP-6492 | Transaction - Pay by link - Pay Later - Customer - Order with negative fixed fee',
		...orders.negative15FixedFee,
		payment: payLater,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6571
		title: 'PCP-6571 | Transaction - Pay by link - Pay Later - Customer - Order with negative percentage fee',
		...orders.negative12PercentFee,
		payment: payLater,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6493
		title: 'PCP-6493 | Transaction - Pay by link - Pay Later - Guest - Order with negative fixed fee',
		...orders.negative15FixedFee,
		payment: payLater,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6572
		title: 'PCP-6572 | Transaction - Pay by link - Pay Later - Guest - Order with negative percentage fee',
		...orders.negative12PercentFee,
		payment: payLater,
		customer: guest,
	},
];
