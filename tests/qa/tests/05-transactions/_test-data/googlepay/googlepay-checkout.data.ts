/**
 * Internal dependencies
 */
import { payments, orders, customers, ShopOrder } from '../../../../resources';

const customer = customers.usa;

export const googlePayCheckout: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-26555
		title: 'PCP-26555 | Transaction - Checkout - Google Pay - Order by customer',
		...orders.default,
		payment: payments.googlePay,
		customer,
	},
];
