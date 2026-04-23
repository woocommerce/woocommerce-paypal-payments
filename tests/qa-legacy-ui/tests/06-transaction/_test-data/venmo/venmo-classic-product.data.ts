/**
 * Internal dependencies
 */
import { orders, merchants, guests, venmo } from '../../../../resources';

/**
 * Venmo is eligible only for USA/USD
 */
const usaOrderData: WooCommerce.ShopOrder = {
	merchant: merchants.usa,
	customer: guests.usa,
	currency: 'USD',
};

export const venmoClassicProductUsa = [
	{
		title: 'PCP-2985 | Transaction - Product (classic checkout) - Venmo - Simple product',
		...orders.default,
		...usaOrderData,
		payment: venmo,
	},
	{
		title: 'PCP-2987 | Transaction - Product (classic checkout) - Venmo - Variable product',
		...orders.variableProduct,
		...usaOrderData,
		payment: venmo,
	},
];
