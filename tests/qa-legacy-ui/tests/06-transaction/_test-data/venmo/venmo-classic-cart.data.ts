/**
 * Internal dependencies
 */
import { venmo, orders, guests, merchants } from '../../../../resources';

/**
 * Venmo is eligible only for USA/USD
 */
const usaOrderData: WooCommerce.ShopOrder = {
	currency: 'USD',
	merchant: merchants.usa,
	customer: guests.usa,
};

export const venmoClassicCartUsa = [
	{
		title: 'PCP-3147 | Transaction - Classic cart - Venmo - Default order @Critical',
		...orders.default,
		...usaOrderData,
		payment: venmo,
	},
];
