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

export const payPalProduct: ShopOrder[] = [
	{
		title: 'PCP-6297 | Transaction - Product - PayPal - Variable product with two not selected attributes @Critical',
		...orders.default,
		payment: payPal,
		products: [
			{
				...products.variationNotSelected100,
				variationToSelect: {
					color: 'Gray',
					size: 'M',
				},
			}
		],
	},
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Product - PayPal - Simple product',
		...orders.default,
		payment: payPal,
		customer: guest,
	},
];

export const payPalProductPayNowOff: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Product - PayPal - Pay Now off - Simple product',
		...orders.default,
		payment: payPal,
		customer: guest,
		isPayNowEnabled: false,
	},
];

export const payPalClassicProductPayNowOff: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Product - PayPal - Pay Now off - Classic checkout - Variable product',
		...orders.default,
		payment: payPal,
		customer,
		products: [ variableProduct ],
		isPayNowEnabled: false,
	},
];
