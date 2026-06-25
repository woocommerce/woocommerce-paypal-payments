/**
 * Internal dependencies
 */
import { payments, orders, ShopOrder, products } from '../../../../resources';

const { payLater } = payments;

export const payLaterProduct: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-6588
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
];
