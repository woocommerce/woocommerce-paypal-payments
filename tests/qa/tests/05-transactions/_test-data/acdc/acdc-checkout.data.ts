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
		title: 'PCP-3217 | Transaction - Checkout - ACDC - Default order @Critical @Smoke',
		...orders.default,
		payment: payments.acdc,
		customer: guest,
	},
	{
		title: 'PCP-3224 | Transaction - Checkout - ACDC - Order by customer',
		...orders.default,
		payment: payments.acdc,
		customer,
	},
];

export const acdcCheckoutExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-3221 | Transaction - Checkout - ACDC - Order with price excluding tax',
		...orders.excludingTax,
		payment: payments.acdc,
		customer: guest,
	},
];

export const acdcCheckoutIntentAuthorized: ShopOrder[] = [
	{
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
		title: 'PCP-1135 | Transaction - Checkout - ACDC - Order with Always trigger 3D secure',
		...orders.default,
		payment: payments.acdc3ds,
		customer: guest,
	},
	{
		title: 'PCP-3225 | Transaction - Checkout - ACDC - Order paid with card requiring 3DS',
		...orders.default,
		payment: payments.acdc3ds,
		customer,
	},
];

export const acdcCheckoutNegativeFee: ShopOrder[] = [
	{
		title: 'PCP-6575 | Transaction - Checkout - ACDC - Customer - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payments.acdc,
		customer,
	},
	{
		title: 'PCP-6576 | Transaction - Checkout - ACDC - Guest - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payments.acdc,
		customer: guest,
	},
];
