/**
 * Internal dependencies
 */
import {
	payments,
	orders,
	ShopOrder,
	guests,
} from '../../../../resources';

const guest = guests.usa;

const { payLater } = payments;

export const payLaterClassicCart: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Classic cart - Pay Later - Default order',
		...orders.default,
		payment: payLater,
		customer: guest,
	},
];

export const payLaterClassicCartPayNowOff: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Classic cart - Pay Later - Pay Now off - Default order',
		...orders.default,
		payment: payLater,
		customer: guest,
		isPayNowEnabled: false,
	},
];
