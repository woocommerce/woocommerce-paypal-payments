/**
 * Internal dependencies
 */
import { customers, orders, payments, ShopOrder } from '../../../resources';

const customer = customers.usa;

const savePaymentMethodData: ShopOrder[] = [
	{
		title: 'PCP-5389 | Vaulting - Transaction - Classic checkout - PayPal - Save payment method @Critical @Smoke',
		...orders.default,
		payment: {
			...payments.payPal,
			saveToAccount: true,
		},
		customer,
	},
	{
		title: 'PCP-1204 | Vaulting - Transaction - Classic checkout - ACDC - Save payment method @Critical @Smoke',
		...orders.default,
		payment: {
			...payments.acdc,
			saveToAccount: true,
		},
		customer,
	},
	{
		title: 'PCP-5388 | Vaulting - Transaction - Classic checkout - ACDC - Do not save payment method',
		...orders.default,
		payment: {
			...payments.acdc,
			saveToAccount: false,
		},
		customer,
	},
];

const acdcAdditionalCardData: ShopOrder[] = [
	{
		title: 'PCP-5387 | Vaulting - Transaction - Classic checkout - ACDC - Pay with card other then saved and do not save it',
		...orders.default,
		payment: {
			...payments.acdc,
			saveToAccount: false,
		},
		customer,
	},
	{
		title: 'PCP-5386 | Vaulting - Transaction - Classic checkout - ACDC - Pay with card other then saved and save it',
		...orders.default,
		payment: {
			...payments.acdc,
			saveToAccount: true,
		},
		customer,
	},
];

const vaultedPaymentMethodData: ShopOrder[] = [
	{
		title: 'PCP-1378 | Vaulting - Transaction - Classic checkout - PayPal - Pay with vaulted account',
		...orders.default,
		payment: {
			...payments.payPal,
			isVaulted: true,
		},
		customer,
	},
	{
		title: 'PCP-1206 | Vaulting - Transaction - Classic checkout - ACDC - Pay with saved card',
		...orders.default,
		payment: {
			...payments.acdc,
			isVaulted: true,
		},
		customer,
	},
];

export const vaultingClassicCheckout = {
	savePaymentMethodData,
	acdcAdditionalCardData,
	vaultedPaymentMethodData,
};
