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
		title: 'PCP-2894 | Transaction - Product - Pay Later - Simple product',
		...orders.default,
		payment: payLater,
		customer,
	},
];

export const payLaterProductPayNowOff: ShopOrder[] = [
	{
		title: 'PCP-6921 | Transaction - Product - Pay Later - Pay Now off - Simple product',
		...orders.default,
		payment: payLater,
		customer: guest,
		isPayNowEnabled: false,
	},
];
