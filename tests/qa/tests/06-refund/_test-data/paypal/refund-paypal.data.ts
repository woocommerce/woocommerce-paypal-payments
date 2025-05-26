/**
 * Internal dependencies
 */
import { payments, orders, ShopRefund, customers, guests } from '../../../../resources';

const { payPal } = payments;
const customer = customers.usa;
const guest = guests.usa;

export const refundPayPalFromCheckout: ShopRefund[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-1394
		title: 'PCP-1394 | Refund - Full - Order from shop (paid via PayPal)',
		...orders.default,
		payment: payPal,
		isApiOrder: false,
		refundPercentage: 100,
		refundOrderStatus: 'refunded',
		refundPaymentStatus: 'REFUNDED',
		customer,
		currency: 'USD',
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-1395
		title: 'PCP-1395 | Refund - Partial - Order from shop (paid via PayPal)',
		...orders.default,
		payment: payPal,
		isApiOrder: false,
		refundPercentage: 50,
		refundOrderStatus: 'processing',
		refundPaymentStatus: 'PARTIALLY_REFUNDED',
		customer,
		currency: 'USD',
	},
];

export const refundPayPalFromPayByLink: ShopRefund[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-1405
		title: 'PCP-1405 | Refund - Full - Order from dashboard (paid via PayPal)',
		...orders.default,
		payment: payPal,
		isApiOrder: true,
		refundPercentage: 100,
		refundOrderStatus: 'refunded',
		refundPaymentStatus: 'REFUNDED',
		customer: guest,
		currency: 'USD',
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-1406
		title: 'PCP-1406 | Refund - Partial - Order from dashboard (paid via PayPal)',
		...orders.default,
		payment: payPal,
		isApiOrder: true,
		refundPercentage: 50,
		refundOrderStatus: 'processing',
		refundPaymentStatus: 'PARTIALLY_REFUNDED',
		customer: guest,
		currency: 'USD',
	},
];
