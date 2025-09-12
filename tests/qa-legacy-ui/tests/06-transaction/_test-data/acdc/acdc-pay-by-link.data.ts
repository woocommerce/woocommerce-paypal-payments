/**
 * Internal dependencies
 */
import { customers, guests, acdc, orders } from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

export const acdcPayByLink = [
	{
		title: 'PCP-1327 | Transaction - Pay by link - ACDC - Guest - Order default @Critical',
		...orders.default,
		payment: acdc,
		customer: guest,
	},
	{
		title: 'PCP-1326 | Transaction - Pay by link - ACDC - Customer - Order default @Critical',
		...orders.default,
		payment: acdc,
		customer,
	},
];
