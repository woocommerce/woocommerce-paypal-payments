/**
 * Internal dependencies
 */
import { payPal, orders } from '../../../../resources';

export const payPalProduct = [
	{
		title: 'PCP-2892 | Transaction - Product - PayPal - Simple product',
		...orders.default,
		payment: payPal,
	},
	{
		title: 'PCP-2893 | Transaction - Product - PayPal - Variable product @Critical',
		...orders.variableProduct,
		payment: payPal,
	},
];

export const payPalProductVerticalButton = [
	{
		title: 'PCP-2890 | Transaction - Product - PayPal - Vertical button layout @Critical',
		...orders.default,
		payment: payPal,
	},
];
