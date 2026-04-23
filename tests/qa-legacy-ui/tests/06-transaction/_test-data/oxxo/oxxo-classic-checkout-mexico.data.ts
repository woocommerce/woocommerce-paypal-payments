/**
 * Internal dependencies
 */
import {
	customers,
	oxxo,
	orders,
	merchants,
	guests,
} from '../../../../resources';

/**
 * Venmo is eligible only for Mexico/MXD
 */
const mexicanOrderData: WooCommerce.ShopOrder = {
	merchant: merchants.mexico,
	customer: guests.mexico,
	payment: oxxo,
	orderStatus: 'pending',
	currency: 'USD',
};

export const oxxoClassicCheckoutMexico = [
	{
		title: 'PCP-1219 | Transaction - Classic checkout - Mexico - OXXO - Default order @Critical',
		...orders.default,
		...mexicanOrderData,
	},
	{
		title: 'PCP-2755 | Transaction - Classic checkout - Mexico - OXXO - Order by customer',
		...orders.byCustomer,
		...mexicanOrderData,
		customer: customers.mexico,
	},
	// {
	// 	title: 'PCP-1228 | Transaction - Classic checkout - Mexico - OXXO - Order with free shipping @Critical',
	// 	...orders.freeShipping,
	// 	...mexicanOrderData,
	// },
	// {
	// 	title: 'PCP-1230 | Transaction - Classic checkout - Mexico - OXXO - Order with fixed coupon',
	// 	...orders.fixedCoupon10,
	// 	...mexicanOrderData,
	// },
	// {
	// 	title: 'PCP-2752 | Transaction - Classic checkout - Mexico - OXXO - Order with percentage coupon',
	// 	...orders.percentCoupon10,
	// 	...mexicanOrderData,
	// },
	// {
	// 	title: 'PCP-2753 | Transaction - Classic checkout - Mexico - OXXO - Order including free product',
	// 	...orders.includingFreeProduct,
	// 	...mexicanOrderData,
	// },
	// {
	// 	title: 'PCP-2754 | Transaction - Classic checkout - Mexico - OXXO - Order with product without image',
	// 	...orders.productWithoutImage,
	// 	...mexicanOrderData,
	// },
];

export const oxxoClassicCheckoutMexicoExcludingTax = [
	{
		title: 'PCP-1236 | Transaction - Classic checkout - Mexico - OXXO - Order with price excluding tax',
		...orders.excludingTax,
		...mexicanOrderData,
	},
];
