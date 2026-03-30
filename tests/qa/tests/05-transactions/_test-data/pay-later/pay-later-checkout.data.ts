/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder } from '../../../../resources';

const { payLater } = payments;

export const payLaterCheckout: ShopOrder[] = [
	{
		title: 'PCP-2864 | Transaction - Checkout - Pay Later - Default order @Critical @Smoke',
		payment: payLater,
		...orders.default,
	},
	{
		title: 'PCP-1650 | Transaction - Checkout - Pay Later - Order by customer',
		payment: payLater,
		...orders.byCustomer,
	},
];

export const payLaterCheckoutExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-1656 | Transaction - Checkout - Pay Later - Order with price excluding tax',
		payment: payLater,
		...orders.excludingTax,
	},
];

export const payLaterCheckoutIntentAuthorized: ShopOrder[] = [
	{
		title: 'PCP-2869 | Transaction - Checkout - Pay Later - Order with Intent Authorized',
		payment: {
			...payLater,
			isAuthorized: true,
		},
		...orders.default,
		orderStatus: 'on-hold',
	},
];
