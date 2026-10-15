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

const { payLater } = payments;

export const payLaterProduct: ShopOrder[] = [
	{
		title: 'PCP-6588 | Transaction - Product - Pay Later - Variable product with two not selected attributes @Critical',
		...orders.default,
		payment: payLater,
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
		title: 'PCP-0000 | Transaction - Product - Pay Later - Simple product',
		...orders.default,
		payment: payLater,
		customer,
	},
];

export const payLaterProductPayNowOff: ShopOrder[] = [
	{
		// TODO: replace PCP-0000 with the Xray key once the test case is created.
		title: 'PCP-0000 | Transaction - Product - Pay Later - Pay Now off - Simple product',
		...orders.default,
		payment: payLater,
		customer: guest,
		isPayNowEnabled: false,
	},
];
