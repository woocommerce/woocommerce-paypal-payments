/**
 * Internal dependencies
 */
import {
	payments,
	orders,
	ShopOrder,
	customers,
	guests,
} from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { payPal } = payments;

export const payPalMiniCart: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Mini cart - PayPal - Default order',
		...orders.default,
		payment: payPal,
		customer: guest,
	},
];

export const payPalMiniCartPayNowOff: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Mini cart - PayPal - Pay Now off - Checkout',
		...orders.default,
		payment: payPal,
		customer: guest,
		isPayNowEnabled: false,
	},
];

export const payPalClassicMiniCartPayNowOff: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Mini cart - PayPal - Pay Now off - Classic checkout',
		...orders.default,
		payment: payPal,
		customer,
		isPayNowEnabled: false,
	},
];
