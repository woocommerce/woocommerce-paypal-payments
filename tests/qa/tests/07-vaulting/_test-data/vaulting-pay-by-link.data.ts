/**
 * Internal dependencies
 */
import { customers, orders, payments, ShopOrder } from '../../../resources';

const customer = customers.usa;

const savePaymentMethodData: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-5390
		title: 'PCP-5390 | Vaulting - Transaction - Pay by link - PayPal - Save payment method @Critical @Smoke',
		...orders.default,
		payment: {
			...payments.payPal,
			saveToAccount: true,
		},
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-5391
		title: 'PCP-5391 | Vaulting - Transaction - Pay by link - ACDC - Save payment method @Critical',
		...orders.default,
		payment: {
			...payments.acdc,
			saveToAccount: true,
		},
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-5392
		title: 'PCP-5392 | Vaulting - Transaction - Pay by link - ACDC - Do not save payment method',
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
		// https://inpsyde.atlassian.net/browse/PCP-5393
		title: 'PCP-5393 | Vaulting - Transaction - Pay by link - ACDC - Pay with card other then saved and do not save it',
		...orders.default,
		payment: {
			...payments.acdc,
			saveToAccount: false,
		},
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-5394
		title: 'PCP-5394 | Vaulting - Transaction - Pay by link - ACDC - Pay with card other then saved and save it',
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
		// https://inpsyde.atlassian.net/browse/PCP-5395
		title: 'PCP-5395 | Vaulting - Transaction - Pay by link - PayPal - Pay with vaulted account @Critical',
		...orders.default,
		payment: {
			...payments.payPal,
			isVaulted: true,
		},
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-5396
		title: 'PCP-5396 | Vaulting - Transaction - Pay by link - ACDC - Pay with saved card @Critical',
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
