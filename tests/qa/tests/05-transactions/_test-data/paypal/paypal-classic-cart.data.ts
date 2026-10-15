/**
 * Internal dependencies
 */
import {
	payments,
	orders,
	ShopOrder,
	customers,
} from '../../../../resources';

const customer = customers.usa;

const { payPal } = payments;

export const payPalClassicCart: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Classic cart - PayPal - Default order',
		...orders.default,
		payment: payPal,
		customer,
	},
];

export const payPalClassicCartPayNowOff: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Classic cart - PayPal - Pay Now off - Default order',
		...orders.default,
		payment: payPal,
		customer,
		isPayNowEnabled: false,
	},
];
