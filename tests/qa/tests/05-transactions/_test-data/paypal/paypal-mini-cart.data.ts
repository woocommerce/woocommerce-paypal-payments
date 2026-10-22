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
		title: 'PCP-5771 | Transaction - Mini cart - PayPal - Default order',
		...orders.default,
		payment: payPal,
		customer: guest,
	},
];

export const payPalMiniCartPayNowOff: ShopOrder[] = [
	{
		title: 'PCP-5773 | Transaction - Mini cart - PayPal - Pay Now off - Checkout',
		...orders.default,
		payment: payPal,
		customer: guest,
		isPayNowEnabled: false,
	},
];

export const payPalClassicMiniCartPayNowOff: ShopOrder[] = [
	{
		title: 'PCP-5772 | Transaction - Mini cart - PayPal - Pay Now off - Classic checkout',
		...orders.default,
		payment: payPal,
		customer,
		isPayNowEnabled: false,
	},
];
