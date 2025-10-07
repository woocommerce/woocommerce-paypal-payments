/**
 * Internal dependencies
 */
import { payPal, orders } from '../../../../resources';

export const payPalClassicProduct = [
	{
		title: 'PCP-1166 | Transaction - Product (classic checkout) - PayPal - Simple product @Critical',
		payment: payPal,
		...orders.default,
	},
	{
		title: 'PCP-1924 | Transaction - Product (classic checkout) - PayPal - Variable product @Critical',
		payment: payPal,
		...orders.variableProduct,
	},
];

export const payPalClassicProductVerticalButton = [
	{
		title: 'PCP-1165 | Transaction - Product (classic checkout) - PayPal - Vertical buttons layout',
		payment: payPal,
		...orders.default,
	},
];
