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

const { payLater } = payments;

export const payLaterMiniCart: ShopOrder[] = [
	{
		title: 'PCP-1193 | Transaction - Mini cart - Pay Later - Default order',
		...orders.default,
		payment: payLater,
		customer,
	},
];

export const payLaterClassicMiniCartPayNowOff: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Mini cart - Pay Later - Pay Now off - Classic checkout',
		...orders.default,
		payment: payLater,
		customer: guest,
		isPayNowEnabled: false,
	},
];
