/**
 * Internal dependencies
 */
import {
	storeConfigSubscriptionUsa,
	payPalVaulted,
	payPal,
	orders,
	pcpConfigUsa,
} from '../../../../resources';

const customer = storeConfigSubscriptionUsa.customer;

const usaOrderData: WooCommerce.ShopOrder = {
	merchant: pcpConfigUsa.merchant,
	customer,
	subscription: { status: 'active' },
	currency: 'USD',
};

export const subscriptionPayPalClassicCheckoutVaulted = [
	{
		title: 'PCP-2889 | Vaulting subscription - Transaction - Classic checkout - PayPal - Order by customer @Critical',
		...orders.subscriptionDefault,
		...usaOrderData,
		payment: payPalVaulted,
	},
	// {
	// 	title: 'PCP-2492 | Subscription - Transaction - Classic checkout - PayPal - Order with free shipping',
	// 	...orders.subscriptionFreeShipping,
	// 	...usaOrderData,
	// 	payment: payPalVaulted,
	// },
];
