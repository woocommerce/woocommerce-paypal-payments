/**
 * Internal dependencies
 */
import {
	payments,
	orders,
	ShopRefund,
	customers,
	guests,
} from '../../../resources';

const { acdc } = payments;
const customer = customers.usa;
const guest = guests.usa;

export const refundAcdcFromCheckout: ShopRefund[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-1396
		title: 'PCP-1396 | Refund - Full - ACDC - Order from shop @Critical @Dev',
		...orders.default,
		payment: acdc,
		isApiOrder: false,
		refundPercentage: 100,
		refundOrderStatus: 'refunded',
		refundPaymentStatus: 'REFUNDED',
		customer,
		currency: 'USD',
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-1397
		title: 'PCP-1397 | Refund - Partial - ACDC - Order from shop @Critical @Dev',
		...orders.default,
		payment: acdc,
		isApiOrder: false,
		refundPercentage: 50,
		refundOrderStatus: 'processing',
		refundPaymentStatus: 'PARTIALLY_REFUNDED',
		customer,
		currency: 'USD',
	},
];

export const refundAcdcFromPayByLink: ShopRefund[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-1409
		title: 'PCP-1409 | Refund - Full - ACDC - Order from dashboard',
		...orders.default,
		payment: acdc,
		isApiOrder: true,
		refundPercentage: 100,
		refundOrderStatus: 'refunded',
		refundPaymentStatus: 'REFUNDED',
		customer: guest,
		currency: 'USD',
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-1410
		title: 'PCP-1410 | Refund - Partial - ACDC - Order from dashboard',
		...orders.default,
		payment: acdc,
		isApiOrder: true,
		refundPercentage: 50,
		refundOrderStatus: 'processing',
		refundPaymentStatus: 'PARTIALLY_REFUNDED',
		customer: guest,
		currency: 'USD',
	},
];
