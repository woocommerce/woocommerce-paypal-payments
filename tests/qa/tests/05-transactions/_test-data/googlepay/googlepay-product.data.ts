/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, products } from '../../../../resources';

const { googlePay } = payments;

export const googlePayProduct: ShopOrder[] = [
	{
		title: 'PCP-6589 | Transaction - Product - Google Pay - Variable product with two not selected attributes @Critical',
		...orders.default,
		payment: googlePay,
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
