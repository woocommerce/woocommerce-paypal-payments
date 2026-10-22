/**
 * Internal dependencies
 */
import {
	payments,
	orders,
	ShopOrder,
	products,
	customers,
	guests,
} from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

const { payPal } = payments;

// Variable product with the variation chosen on the product page or sent to the cart
const variableProduct = {
	...products.variationNotSelected100,
	variationToSelect: { color: 'Gray', size: 'M' },
};

export const payPalCheckout: ShopOrder[] = [
	{
		title: 'PCP-1641 | Transaction - Checkout - PayPal - Default order @Critical @Smoke',
		...orders.default,
		payment: payPal,
		customer: guest,
	},
	{
		title: 'PCP-1643 | Transaction - Checkout - PayPal - Order by customer',
		...orders.default,
		payment: payPal,
		customer
	},
];

export const payPalCheckoutExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-1649 | Transaction - Checkout - PayPal - Order with price excluding tax',
		...orders.excludingTax,
		payment: payPal,
		customer: guest,
	},
];

export const payPalCheckoutIntentAuthorized: ShopOrder[] = [
	{
		title: 'PCP-2868 | Transaction - Checkout - PayPal - Order with Intent Authorized',
		...orders.default,
		payment: {
			...payPal,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
		customer: guest,
	},
];

export const payPalCheckoutNegativeFee: ShopOrder[] = [
	{
		title: 'PCP-6568 | Transaction - Checkout - PayPal - Customer - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payPal,
		customer,
	},
	{
		title: 'PCP-6569 | Transaction - Checkout - PayPal - Guest - Order with negative fee snippet',
		...orders.negative12Fee,
		payment: payPal,
		customer: guest,
	},
];

export const payPalCheckoutVariable: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Checkout - PayPal - Variable product',
		...orders.default,
		payment: payPal,
		customer: guest,
		products: [ variableProduct ],
	},
];

export const payPalCheckoutPayNowOff: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Checkout - PayPal - Pay Now off - Default order',
		...orders.default,
		payment: payPal,
		customer: guest,
		isPayNowEnabled: false,
	},
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Checkout - PayPal - Pay Now off - Order by customer',
		...orders.default,
		payment: payPal,
		customer,
		isPayNowEnabled: false,
	},
];

export const payPalCheckoutEmptyForm: ShopOrder[] = [
	{
		// Fails on SDK v6: lands on the review page instead of order received (PCP-7032)
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Checkout - PayPal - Empty checkout form - Default order',
		...orders.default,
		payment: payPal,
		customer: guest,
		isCheckoutFormEmpty: true,
	},
];
