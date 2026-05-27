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

const customer = customers.usa;
const guest = guests.usa;

export const acdcClassicCheckout: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-1202
		title: 'PCP-1202 | Transaction - Classic checkout - ACDC - Default order @Critical @Smoke',
		...orders.default,
		payment: payments.acdc,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-2743
		title: 'PCP-2743 | Transaction - Classic checkout - ACDC - Order by customer',
		...orders.default,
		payment: payments.acdc,
		customer,
	},
];

export const acdcClassicCheckoutExcludingTax: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-1259
		title: 'PCP-1259 | Transaction - Classic checkout - ACDC - Order with price excluding tax',
		...orders.excludingTax,
		payment: payments.acdc,
	},
];

export const acdcClassicCheckoutIntentAuthorized: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/
		title: 'PCP-0000 | Transaction - Classic checkout - ACDC - Order with Intent Authorized',
		...orders.default,
		payment: {
			...payments.acdc,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
		customer: guest,
	},
];

export const acdcClassicCheckout3ds: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/
		title: 'PCP-0000 | Transaction - Classic checkout - ACDC - Contingency for 3D Secure = Always trigger 3D secure',
		...orders.default,
		payment: payments.acdc3ds,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-1209
		title: 'PCP-1209 | Transaction - Classic checkout - ACDC - Order paid with card requiring 3DS',
		...orders.default,
		payment: payments.acdc3ds,
		customer: guest,
	},
];
