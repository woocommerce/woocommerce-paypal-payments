/**
 * Internal dependencies
 */
import {
	customers,
	guests,
	payments,
	orders,
	ShopOrder,
} from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

export const acdcPayByLink: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-1327
		title: 'PCP-1327 | Transaction - Pay by link - ACDC - Guest - Default order @Critical @Smoke',
		...orders.default,
		payment: payments.acdc,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-1326
		title: 'PCP-1326 | Transaction - Pay by link - ACDC - Customer - Default order @Critical',
		...orders.default,
		payment: payments.acdc,
		customer,
	},
];

export const acdcPayByLinkExcludingTax: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/
		title: 'PCP-0000 | Transaction - Pay by link - ACDC - Order with price excluding tax',
		...orders.excludingTax,
		payment: payments.acdc,
		customer: guest,
	},
];

export const acdcPayByLinkIntentAuthorized: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/
		title: 'PCP-0000 | Transaction - Pay by link - ACDC - Order with Intent Authorized',
		...orders.default,
		payment: {
			...payments.acdc,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
		customer: guest,
	},
];

export const acdcPayByLink3ds: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/
		title: 'PCP-0000 | Transaction - Pay by link - ACDC - Contingency for 3D Secure = Always trigger 3D secure',
		...orders.default,
		payment: payments.acdc3ds,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/
		title: 'PCP-0000 | Transaction - Pay by link - ACDC - Order paid with card requiring 3DS',
		...orders.default,
		payment: payments.acdc3ds,
		customer: guest,
	},
];
