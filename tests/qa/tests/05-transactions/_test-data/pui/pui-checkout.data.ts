/**
 * Internal dependencies
 */
import {
	payments,
	orders,
	customers,
	guests,
	ShopOrder,
} from '../../../../resources';

const customer = customers.germany;
const guest = guests.germany;
const currency = 'EUR';
const { pui } = payments;

export const puiCheckout: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-6640
		title: 'PCP-6640 | Transaction - Checkout - Pay upon Invoice - Germany - Guest - Default order @Critical',
		...orders.default,
		payment: pui,
		customer: guest,
		orderStatus: 'on-hold',
		currency,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6641
		title: 'PCP-6641 | Transaction - Checkout - Pay upon Invoice - Germany - Customer - Default order @Critical',
		...orders.default,
		payment: pui,
		customer,
		orderStatus: 'on-hold',
		currency,
	},
];
