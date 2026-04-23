/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder } from '../../../../resources';

const { payPal } = payments;

export const payPalPayByLink = [
	{
		title: 'PCP-2886 | Transaction - Pay by link - PayPal - Customer - Default order @Critical @Smoke',
		...orders.byCustomer,
		payment: payPal,
	},
	{
		title: 'PCP-2887 | Transaction - Pay by link - PayPal - Guest - Default order @Critical',
		...orders.default,
		payment: payPal,
	},
];

export const payPalPayByLinkExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-0000 | Transaction - Pay by link - PayPal - Order with price excluding tax',
		payment: payPal,
		...orders.excludingTax,
	},
];

export const payPalPayByLinkIntentAuthorized: ShopOrder[] = [
	{
		title: 'PCP-3333 | Transaction - Pay by link - PayPal - Order with Intent Authorized',
		payment: {
			...payPal,
			isAuthorized: true,
		},
		...orders.default,
		orderStatus: 'on-hold',
	},
];
