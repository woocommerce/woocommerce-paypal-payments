/**
 * Internal dependencies
 */
import {
	guests,
	payments,
	orders,
	merchants,
	customers,
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

export const venmoClassicCheckoutUsa: ShopOrder[] = [
	{
		title: 'PCP-2911 | Transaction - Classic checkout - USA - Venmo - Default order',
		payment: venmo,
		...orders.default,
		...usaOrderData,
	},
	{
		title: 'PCP-2984 | Transaction - Classic checkout - USA - Venmo - Order by customer',
		payment: venmo,
		...orders.byCustomer,
		...usaOrderData,
		customer: customers.usa,
	},
];

export const venmoClassicCheckoutUsaExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-2981 | Transaction - Classic checkout - USA - Venmo - Order with price excluding tax',
		payment: venmo,
		...orders.excludingTax,
		...usaOrderData,
	},
];
