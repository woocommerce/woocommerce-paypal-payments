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

export const acdcCheckout: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-3217
		title: 'PCP-3217 | Transaction - Checkout - ACDC - Default order @Critical @Smoke',
		...orders.default,
		payment: payments.acdc,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-3224
		title: 'PCP-3224 | Transaction - Checkout - ACDC - Order by customer',
		...orders.default,
		payment: payments.acdc,
		customer,
	},
];

export const acdcCheckoutExcludingTax: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-3221
		title: 'PCP-3221 | Transaction - Checkout - ACDC - Order with price excluding tax',
		...orders.excludingTax,
		payment: payments.acdc,
		customer: guest,
	},
];

export const acdcCheckoutIntentAuthorized: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-3228
		title: 'PCP-3228 | Transaction - Checkout - ACDC - Order with Intent Authorized',
		...orders.default,
		payment: {
			...payments.acdc,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
		customer: guest,
	},
];

export const acdcCheckout3ds: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-1135
		title: 'PCP-1135 | Transaction - Checkout - ACDC - Order with Always trigger 3D secure',
		...orders.default,
		payment: payments.acdc3ds,
		customer: guest,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-3225
		title: 'PCP-3225 | Transaction - Checkout - ACDC - Order paid with card requiring 3DS',
		...orders.default,
		payment: payments.acdc3ds,
		customer,
	},
];
