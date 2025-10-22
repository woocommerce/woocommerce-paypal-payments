/**
 * Internal dependencies
 */
import {
	payUponInvoice,
	orders,
	merchants,
	customers,
	guests,
} from '../../../../resources';

/**
 * Venmo is eligible only for Germany/EUR
 */
const germanOrderData: WooCommerce.ShopOrder = {
	merchant: merchants.germany,
	customer: guests.germany,
	payment: payUponInvoice,
	orderStatus: 'on-hold',
	currency: 'EUR',
};

export const payUponInvoiceClassicCheckoutGermany = [
	{
		// FAIL: PayPal order status Expected: "PENDING_APPROVAL", Received: "COMPLETED"
		title: 'PCP-1216 | Transaction - Germany - Classic checkout - Pay upon Invoice - Default order @Critical',
		...orders.default,
		...germanOrderData,
	},
	{
		title: 'PCP-2751 | Transaction - Germany - Classic checkout - Pay upon Invoice - Order by customer',
		...orders.byCustomer,
		...germanOrderData,
		customer: customers.germany,
	},
	// {
	// 	title: 'PCP-1239 | Transaction - Germany - Classic checkout - Pay upon Invoice - Order with free shipping',
	// 	...orders.freeShipping,
	// 	...germanOrderData,
	// },
	// {
	// 	title: 'PCP-1242 | Transaction - Germany - Classic checkout - Pay upon Invoice - Order with fixed coupon',
	// 	...orders.fixedCoupon10,
	// 	...germanOrderData,
	// },
	// {
	// 	title: 'PCP-2748 | Transaction - Germany - Classic checkout - Pay upon Invoice - Order with percentage coupon',
	// 	...orders.percentCoupon10,
	// 	...germanOrderData,
	// },
	// {
	// 	title: 'PCP-2749 | Transaction - Germany - Classic checkout - Pay upon Invoice - Order including free product',
	// 	...orders.includingFreeProduct,
	// 	...germanOrderData,
	// },
	// {
	// 	title: 'PCP-2750 | Transaction - Germany - Classic checkout - Pay upon Invoice - Order with product without image',
	// 	...orders.productWithoutImage,
	// 	...germanOrderData,
	// },
];

export const payUponInvoiceClassicCheckoutGermanyExcludingTax = [
	{
		title: 'PCP-1246 | Transaction - Germany - Classic checkout - Pay upon Invoice - Order with price excluding tax',
		...orders.excludingTax,
		...germanOrderData,
	},
];
