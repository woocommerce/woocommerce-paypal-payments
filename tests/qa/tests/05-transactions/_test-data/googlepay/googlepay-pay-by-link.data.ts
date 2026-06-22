/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { googlePay } = payments;

export const googlePayPayByLink: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-
		title: 'PCP-6496 | Transaction - Pay by link - Google Pay - Customer - Order with negative fixed fee',
		...orders.negative15FixedFee,
		payment: googlePay,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6577
		title: 'PCP-6577 | Transaction - Pay by link - Google Pay - Customer - Order with negative percentage fee',
		...orders.negative12PercentFee,
		payment: googlePay,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-
		title: 'PCP-6497 | Transaction - Pay by link - Google Pay - Guest - Order with negative fixed fee',
		...orders.negative15FixedFee,
		payment: googlePay,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6578
		title: 'PCP-6578 | Transaction - Pay by link - Google Pay - Guest - Order with negative percentage fee',
		...orders.negative12PercentFee,
		payment: googlePay,
		customer: guest,
	},
];
