/**
 * Internal dependencies
 */
import { debitOrCreditCard, orders } from '../../../../resources';

export const debitOrCreditCardClassicCheckout = [
	{
		title: 'PCP-1199 | Transaction - Classic checkout - Debit or Credit Card - Default order @Critical',
		...orders.default,
		payment: debitOrCreditCard,
	},
	{
		title: 'PCP-2739 | Transaction - Classic checkout - Debit or Credit Card - Order by customer',
		...orders.byCustomer,
		payment: debitOrCreditCard,
	},
	// {
	// 	title: 'PCP-1279 | Transaction - Classic checkout - Debit or Credit Card - Order with free shipping',
	// 	...orders.freeShipping,
	// 	payment: debitOrCreditCard,
	// },
	// {
	// 	title: 'PCP-1281 | Transaction - Classic checkout - Debit or Credit Card - Order with fixed coupon',
	// 	...orders.fixedCoupon10,
	// 	payment: debitOrCreditCard,
	// },
	// {
	// 	title: 'PCP-2736 | Transaction - Classic checkout - Debit or Credit Card - Order with percentage coupon',
	// 	...orders.percentCoupon10,
	// 	payment: debitOrCreditCard,
	// },
	// {
	// 	title: 'PCP-2737 | Transaction - Classic checkout - Debit or Credit Card - Order including free product',
	// 	...orders.includingFreeProduct,
	// 	payment: debitOrCreditCard,
	// },
	// {
	// 	title: 'PCP-2738 | Transaction - Classic checkout - Debit or Credit Card - Order with product without image',
	// 	...orders.productWithoutImage,
	// 	payment: debitOrCreditCard,
	// },
	// {
	// 	title: 'PCP-3044 | Transaction - Classic checkout - Debit or Credit card - Order with multiple products',
	// 	...orders.multipleProducts,
	// 	payment: debitOrCreditCard,
	// },
];

export const debitOrCreditCardClassicCheckoutExcludingTax = [
	{
		title: 'PCP-1285 | Transaction - Classic checkout - Debit or Credit Card - Order with price excluding tax',
		...orders.excludingTax,
		payment: debitOrCreditCard,
	},
];

export const debitOrCreditCardClassicCheckoutIntentAuthorized = [
	{
		title: 'PCP-2758 | Transaction - Classic checkout - Debit or Credit Card - Order with Intent Authorized',
		...orders.default,
		payment: {
			...debitOrCreditCard,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
	},
];
