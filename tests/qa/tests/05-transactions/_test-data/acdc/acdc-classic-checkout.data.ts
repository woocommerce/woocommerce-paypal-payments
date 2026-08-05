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
		title: 'PCP-1202 | Transaction - Classic checkout - ACDC - Default order @Critical @Smoke',
		...orders.default,
		payment: payments.acdc,
		customer: guest,
	},
	{
		title: 'PCP-2743 | Transaction - Classic checkout - ACDC - Order by customer',
		...orders.default,
		payment: payments.acdc,
		customer,
	},
];

export const acdcClassicCheckoutExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-1259 | Transaction - Classic checkout - ACDC - Order with price excluding tax',
		...orders.excludingTax,
		payment: payments.acdc,
	},
];

export const acdcClassicCheckoutIntentAuthorized: ShopOrder[] = [
	{
		title: 'PCP-5740 | Transaction - Classic checkout - ACDC - Customer - Order with intent Authorize',
		...orders.default,
		payment: {
			...payments.acdc,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
		customer,
	},
	{
		title: 'PCP-5741 | Transaction - Classic checkout - ACDC - Guest - Order with intent Authorize',
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
		title: 'PCP-5429 | Transaction - Classic checkout - ACDC - Contingency for 3D Secure = Always trigger 3D secure',
		...orders.default,
		payment: payments.acdc3ds,
		customer: guest,
	},
	{
		title: 'PCP-1209 | Transaction - Classic checkout - ACDC - Order paid with card requiring 3DS',
		...orders.default,
		payment: payments.acdc3ds,
		customer: guest,
	},
];

export const acdcClassicCheckoutNegativeFee: ShopOrder[] = [
	{
		title: 'PCP-6608 | Transaction - Classic checkout - ACDC - Customer - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payments.acdc,
		customer,
	},
	{
		title: 'PCP-6609 | Transaction - Classic checkout - ACDC - Guest - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payments.acdc,
		customer: guest,
	},
];
