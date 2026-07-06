/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, products } from '../../../../resources';

const { payPal } = payments;

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
];
