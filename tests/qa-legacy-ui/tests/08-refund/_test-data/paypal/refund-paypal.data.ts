/**
 * External dependencies
 */
import { getHalfPrice } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { payPal, orders } from '../../../../resources';

export const refundPayPalFromCheckout = [
	{
		title: 'PCP-1394 | Refund - Full - Order from shop (paid via PayPal) @Critical',
		...orders.byCustomer,
		payment: payPal,
		isApiOrder: false,
		isFullRefund: true,
		refundOrderStatus: 'refunded',
		refundPaymentStatus: 'REFUNDED',
	},
	{
		title: 'PCP-1395 | Refund - Partial - Order from shop (paid via PayPal) @Critical',
		...orders.byCustomer,
		payment: payPal,
		isApiOrder: false,
		isFullRefund: false,
		refundOrderStatus: 'processing',
		refundPaymentStatus: 'PARTIALLY_REFUNDED',
	},
];

export const refundPayPalFromPayByLink = [
	{
		title: 'PCP-1405 | Refund - Full - Order from dashboard (paid via PayPal) @Critical',
		...orders.default,
		payment: payPal,
		isApiOrder: true,
		isFullRefund: true,
		refundOrderStatus: 'refunded',
		refundPaymentStatus: 'REFUNDED',
	},
	{
		title: 'PCP-1406 | Refund - Partial - Order from dashboard (paid via PayPal) @Critical',
		...orders.default,
		payment: payPal,
		isApiOrder: true,
		isFullRefund: false,
		refundOrderStatus: 'processing',
		refundPaymentStatus: 'PARTIALLY_REFUNDED',
	},
];
