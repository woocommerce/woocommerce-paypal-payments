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

const { payLater } = payments;

export const payLaterCart: ShopOrder[] = [
	{
		title: 'PCP-2858 | Transaction - Cart - Pay Later - Default order',
		...orders.default,
		payment: payLater,
		customer,
	},
];

export const payLaterCartPayNowOff: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Cart - Pay Later - Pay Now off - Variable product',
		...orders.default,
		payment: payLater,
		customer: guest,
		products: [ variableProduct ],
		isPayNowEnabled: false,
	},
];
