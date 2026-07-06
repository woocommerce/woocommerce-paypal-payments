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

export const puiPayByLink: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-1329
		title: 'PCP-1329 | Transaction - Pay by Link - Pay upon Invoice - Germany - Guest - Default order @Critical',
		...orders.default,
		payment: pui,
		customer: guest,
		currency,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-1328
		title: 'PCP-1328 | Transaction - Pay by Link - Pay upon Invoice - Germany - Customer - Default order @Critical',
		...orders.default,
		payment: pui,
		customer,
		currency,
	},
];
