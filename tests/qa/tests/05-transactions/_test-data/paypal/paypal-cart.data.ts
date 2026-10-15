/**
 * Internal dependencies
 */
import {
	payments,
	orders,
	products,
	ShopOrder,
	customers,
	guests,
} from '../../../../resources';

const customer = customers.usa;
const guest = guests.usa;

// Variable product with the variation chosen on the product page or sent to the cart
const variableProduct = {
	...products.variationNotSelected100,
	variationToSelect: { color: 'Gray', size: 'M' },
};

const { payPal } = payments;

export const payPalCart: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Cart - PayPal - Default order',
		...orders.default,
		payment: payPal,
		customer: guest,
	},
];

export const payPalCartPayNowOff: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Cart - PayPal - Pay Now off - Variable product',
		...orders.default,
		payment: payPal,
		customer: guest,
		products: [ variableProduct ],
		isPayNowEnabled: false,
	},
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Cart - PayPal - Pay Now off - Order by customer',
		...orders.default,
		payment: payPal,
		customer,
		isPayNowEnabled: false,
	},
];
