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

const { acdc, acdc3ds } = payments;

export const acdcPayByLink: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-1327
		title: 'PCP-1327 | Transaction - Pay by link - ACDC - Guest - Default order @Critical',
		...orders.default,
		payment: acdc,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-1326
		title: 'PCP-1326 | Transaction - Pay by link - ACDC - Customer - Default order @Critical',
		...orders.default,
		payment: acdc,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6494
		title: 'PCP-6494 | Transaction - Pay by link - ACDC - Customer - Order with negative fixed fee',
		...orders.negative15FixedFee,
		payment: acdc,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6575
		title: 'PCP-6575 | Transaction - Pay by link - ACDC - Customer - Order with negative percentage fee',
		...orders.negative12PercentFee,
		payment: acdc,
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6495
		title: 'PCP-6495 | Transaction - Pay by link - ACDC - Guest - Order with negative fixed fee',
		...orders.negative15FixedFee,
		payment: acdc,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-6576
		title: 'PCP-6576 | Transaction - Pay by link - ACDC - Guest - Order with negative percentage fee',
		...orders.negative12PercentFee,
		payment: acdc,
		customer: guest,
	},
];

export const acdcPayByLinkExcludingTax: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-5430
		title: 'PCP-5430 | Transaction - Pay by link - ACDC - Order with price excluding tax',
		...orders.excludingTax,
		payment: acdc,
		customer: guest,
	},
];

export const acdcPayByLinkIntentAuthorized: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-5431
		title: 'PCP-5431 | Transaction - Pay by link - ACDC - Order with Intent Authorized',
		...orders.default,
		payment: {
			...acdc,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
		customer: guest,
	},
];

export const acdcPayByLink3ds: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-5432
		title: 'PCP-5432 | Transaction - Pay by link - ACDC - Contingency for 3D Secure = Always trigger 3D secure',
		...orders.default,
		payment: acdc3ds,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-5433
		title: 'PCP-5433 | Transaction - Pay by link - ACDC - Order paid with card requiring 3DS',
		...orders.default,
		payment: acdc3ds,
		customer: guest,
	},
];
