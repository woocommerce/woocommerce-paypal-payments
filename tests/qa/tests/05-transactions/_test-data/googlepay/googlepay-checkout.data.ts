/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, customers, guests } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

export const googlePayCheckout: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-2655
		title: 'PCP-2655 | Transaction - Checkout - Google Pay - Order by customer @Critical',
		...orders.default,
		payment: payments.googlePay,
		customer,
	},
];

export const googlePayCheckoutNegativeFee: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-6577
		title: 'PCP-6577 | Transaction - Checkout - Google Pay - Customer - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payments.googlePay,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6578
		title: 'PCP-6578 | Transaction - Checkout - Google Pay - Guest - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payments.googlePay,
		customer: guest,
	},
];
