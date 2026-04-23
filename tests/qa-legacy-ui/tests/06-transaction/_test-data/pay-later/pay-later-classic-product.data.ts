/**
 * Internal dependencies
 */
import { payLater, orders } from '../../../../resources';

export const payLaterClassicProduct = [
	{
		title: 'PCP-1168 | Transaction - Product (classic checkout) - Pay Later - Simple product @Critical',
		payment: payLater,
		...orders.default,
	},
	{
		title: 'PCP-2888 | Transaction - Product (classic checkout) - Pay Later - Variable product',
		payment: payLater,
		...orders.variableProduct,
	},
];

export const payLaterClassicProductVerticalButton = [
	{
		title: 'PCP-1167 | Transaction - Product (classic checkout) - Pay Later - Vertical buttons layout',
		payment: payLater,
		...orders.default,
	},
];
