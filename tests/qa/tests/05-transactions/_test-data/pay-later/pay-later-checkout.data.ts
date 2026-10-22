/**
 * Internal dependencies
 */
import {
	payments,
	orders,
	ShopOrder,
	customers,
	guests,
} from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { payLater } = payments;

export const payLaterCheckout: ShopOrder[] = [
	{
		title: 'PCP-2864 | Transaction - Checkout - Pay Later - Default order @Critical',
		payment: payLater,
		...orders.default,
	},
	{
		title: 'PCP-1650 | Transaction - Checkout - Pay Later - Order by customer',
		payment: payLater,
		...orders.byCustomer,
	},
];

export const payLaterCheckoutExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-1656 | Transaction - Checkout - Pay Later - Order with price excluding tax',
		payment: payLater,
		...orders.excludingTax,
	},
];

export const payLaterCheckoutIntentAuthorized: ShopOrder[] = [
	{
		title: 'PCP-2869 | Transaction - Checkout - Pay Later - Order with Intent Authorized',
		payment: {
			...payLater,
			isAuthorized: true,
		},
		...orders.default,
		orderStatus: 'on-hold',
	},
];

export const payLaterCheckoutNegativeFee: ShopOrder[] = [
	{
		title: 'PCP-6571 | Transaction - Checkout - Pay Later - Customer - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payLater,
		customer,
	},
	{
		title: 'PCP-6572 | Transaction - Checkout - Pay Later - Guest - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payLater,
		customer: guest,
	},
];

export const payLaterCheckoutPayNowOff: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Checkout - Pay Later - Pay Now off - Default order',
		...orders.default,
		payment: payLater,
		customer: guest,
		isPayNowEnabled: false,
	},
];

export const payLaterCheckoutEmptyForm: ShopOrder[] = [
	{
		// Fails on SDK v6: lands on the review page instead of order received (PCP-7032)
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Checkout - Pay Later - Empty checkout form - Default order',
		...orders.default,
		payment: payLater,
		customer: guest,
		isCheckoutFormEmpty: true,
	},
];
