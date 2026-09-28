/**
 * Internal dependencies
 */
import { payments, orders, ShopRefund, customers } from '../../../resources';

const { payPal } = payments;
const customer = customers.usa;

export const refundPayPalFromPayPalDashboard: ShopRefund[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Refund - Full - PayPal - Refunded on PayPal side (webhook) @Critical',
		...orders.default,
		payment: payPal,
		isApiOrder: false,
		refundPercentage: 100,
		refundOrderStatus: 'refunded',
		refundPaymentStatus: 'REFUNDED',
		customer,
		currency: 'USD',
	},
];
