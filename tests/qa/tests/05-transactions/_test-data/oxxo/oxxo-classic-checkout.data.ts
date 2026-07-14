/**
 * Internal dependencies
 */
import {
	payments,
	orders,
	customers,
	guests,
	merchants,
	ShopOrder,
} from '../../../../resources';

const customer = customers.mexico;
const guest = guests.mexico;
const merchant = merchants.mexico;

export const oxxoClassicCheckout: ShopOrder[] = [
	{
		title: 'PCP-1219 | Transaction - Classic checkout - OXXO - Mexico - Default order @Critical',
		...orders.default,
		payment: payments.oxxo,
		customer: guest,
		merchant,
	},
	{
		title: 'PCP-2755 | Transaction - Classic checkout - OXXO - Mexico - Order by customer',
		...orders.default,
		payment: payments.oxxo,
		customer,
		merchant,
	},
];
