/**
 * Internal dependencies
 */
import { customers, orders, payments, ShopOrder } from '../../../resources';

const customer = customers.usa;

const savePaymentMethodData: ShopOrder[] = [
	{
		title: 'PCP-0000 | Vaulting - Transaction - Pay by link - PayPal - Save payment method',
		...orders.default,
		payment: {
			...payments.payPal,
			saveToAccount: true,
		},
		customer,
	},
	{
		title: 'PCP-0000 | Vaulting - Transaction - Pay by link - ACDC - Save payment method',
		...orders.default,
		payment: {
			...payments.acdc,
			saveToAccount: true,
		},
		customer,
	},
	{
		title: 'PCP-0000 | Vaulting - Transaction - Pay by link - ACDC - Do not save payment method',
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
		title: 'PCP-0000 | Vaulting - Transaction - Pay by link - ACDC - Pay with card other then saved and do not save it',
		...orders.default,
		payment: {
			...payments.acdc,
			saveToAccount: false,
		},
		customer,
	},
	{
		title: 'PCP-0000 | Vaulting - Transaction - Pay by link - ACDC - Pay with card other then saved and save it',
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
		title: 'PCP-0000 | Vaulting - Transaction - Pay by link - PayPal - Pay with vaulted account',
		...orders.default,
		payment: {
			...payments.payPal,
			isVaulted: true,
		},
		customer,
	},
	{
		title: 'PCP-0000 | Vaulting - Transaction - Pay by link - ACDC - Pay with saved card',
		...orders.default,
		payment: {
			...payments.acdc,
			isVaulted: true,
		},
		customer,
	},
];

export const vaultingPayByLink = {
	savePaymentMethodData,
	acdcAdditionalCardData,
	vaultedPaymentMethodData,
};
