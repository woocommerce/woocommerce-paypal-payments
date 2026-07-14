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

export const puiClassicCheckout: ShopOrder[] = [
	{
		title: 'PCP-1216 | Transaction - Classic checkout - Pay upon Invoice - Germany - Guest - Default order @Critical',
		...orders.default,
		payment: pui,
		customer: guest,
		currency,
	},
	{
		title: 'PCP-2751 | Transaction - Classic checkout - Pay upon Invoice - Germany - Customer - Default order @Critical',
		...orders.default,
		payment: pui,
		customer,
		currency,
	},
];

export const puiClassicCheckoutExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-1246 | Transaction - Classic checkout - Pay upon Invoice - Germany - Guest - Order with price excluding tax',
		...orders.excludingTax,
		payment: pui,
		customer: guest,
		currency,
	},
];

export const puiClassicCheckoutNegativeFee: ShopOrder[] = [
	{
		title: 'PCP-6642 | Transaction - Classic checkout - Pay upon Invoice - Germany - Guest - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: pui,
		customer: guest,
		currency,
	},
];
