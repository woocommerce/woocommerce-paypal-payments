/**
 * Internal dependencies
 */
import { payLater, orders } from '../../../../resources';

export const payLaterProduct = [
	{
		title: 'PCP-2894 | Transaction - Product - Pay Later - Simple product',
		...orders.default,
		payment: payLater,
	},
	{
		title: 'PCP-2895 | Transaction - Product - Pay Later - Variable product @Critical',
		...orders.variableProduct,
		payment: payLater,
	},
];

export const payLaterProductVerticalButton = [
	{
		title: 'PCP-2891 | Transaction - Product - Pay Later - Vertical button layout @Critical',
		...orders.default,
		payment: payLater,
	},
];
