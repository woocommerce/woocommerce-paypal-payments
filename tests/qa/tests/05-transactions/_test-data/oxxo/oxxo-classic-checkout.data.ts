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
		// https://inpsyde.atlassian.net/browse/PCP-6324
		title: 'PCP-6324 | Transaction - Classic checkout - OXXO - Default order @Critical',
		...orders.default,
		payment: payments.oxxo,
		customer: guest,
		merchant,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6325
		title: 'PCP-6325 | Transaction - Classic checkout - OXXO - Order by customer',
		...orders.default,
		payment: payments.oxxo,
		customer,
		merchant,
	},
];
