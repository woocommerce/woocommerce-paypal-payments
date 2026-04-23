/**
 * Internal dependencies
 */
import {
	guests,
	venmo,
	orders,
	merchants,
	customers,
} from '../../../../resources';

/**
 * Venmo is eligible only for USA/USD
 */
const usaOrderData: WooCommerce.ShopOrder = {
	currency: 'USD',
	merchant: merchants.usa,
	customer: guests.usa,
};

export const venmoClassicCheckoutUsa = [
	{
		title: 'PCP-2911 | Transaction - Classic checkout - USA - Venmo - Default order @Critical',
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
	// {
	// 	title: 'PCP-2978 | Transaction - Classic checkout - USA - Venmo - Order with free shipping',
	// 	payment: venmo,
	// 	...orders.freeShipping,
	// 	...usaOrderData,
	// },
	// {
	// 	title: 'PCP-2979 | Transaction - Classic checkout - USA - Venmo - Order with fixed coupon',
	// 	payment: venmo,
	// 	...orders.fixedCoupon10,
	// 	...usaOrderData,
	// },
	// {
	// 	title: 'PCP-2980 | Transaction - Classic checkout - USA - Venmo - Order with percentage coupon',
	// 	payment: venmo,
	// 	...orders.percentCoupon10,
	// 	...usaOrderData,
	// },
	// {
	// 	title: 'PCP-2982 | Transaction - Classic checkout - USA - Venmo - Order including free product',
	// 	payment: venmo,
	// 	...orders.includingFreeProduct,
	// 	...usaOrderData,
	// },
	// {
	// 	title: 'PCP-2983 | Transaction - Classic checkout - USA - Venmo - Order with product without image',
	// 	payment: venmo,
	// 	...orders.productWithoutImage,
	// 	...usaOrderData,
	// },
	// {
	// 	title: 'PCP-3103 | Transaction - Classic checkout - USA - Venmo - Order with multiple products',
	// 	payment: venmo,
	// 	...orders.multipleProducts,
	// 	...usaOrderData,
	// },
];

export const venmoClassicCheckoutUsaExcludingTax = [
	{
		title: 'PCP-2981 | Transaction - Classic checkout - USA - Venmo - Order with price excluding tax',
		payment: venmo,
		...orders.excludingTax,
		...usaOrderData,
	},
];
