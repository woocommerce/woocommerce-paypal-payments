/**
 * Internal dependencies
 */
import {
	payments,
	orders,
	guests,
	merchants,
	ShopOrder,
} from '../../../../resources';

const { venmo } = payments;

/**
 * Venmo is eligible only for USA/USD
 */
const usaOrderData: WooCommerce.ShopOrder = {
	currency: 'USD',
	merchant: merchants.usa,
	customer: guests.usa,
};

export const venmoClassicCartUsa: ShopOrder[] = [
	{
		title: 'PCP-3147 | Transaction - Classic cart - Venmo - Default order',
		...orders.default,
		...usaOrderData,
		payment: venmo,
	},
];
