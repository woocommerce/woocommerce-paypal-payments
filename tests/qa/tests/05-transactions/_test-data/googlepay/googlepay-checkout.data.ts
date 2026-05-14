/**
 * Internal dependencies
 */
import { payments, orders, customers, ShopOrder } from '../../../../resources';

const customer = customers.usa;

export const googlePayCheckout: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-2655
		title: 'PCP-2655 | Transaction - Checkout - Google Pay - Order by customer',
		...orders.default,
		payment: payments.googlePay,
		customer,
	},
];
