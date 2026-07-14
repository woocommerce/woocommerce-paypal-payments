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
		title: 'PCP-6640 | Transaction - Checkout - Pay upon Invoice - Germany - Guest - Default order @Critical',
		...orders.default,
		payment: pui,
		customer: guest,
		currency,
	},
	{
		title: 'PCP-6641 | Transaction - Checkout - Pay upon Invoice - Germany - Customer - Default order @Critical',
		...orders.default,
		payment: pui,
		customer,
		currency,
	},
];
