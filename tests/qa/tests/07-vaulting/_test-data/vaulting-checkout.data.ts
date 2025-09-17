/**
 * Internal dependencies
 */
import { customers, orders, payments, ShopOrder } from '../../../resources';

const customer = customers.usa;

const savePaymentMethodData: ShopOrder[] = [
	{
		// FAIL: vaulted PayPal button is not displayed, "Saved token for ppcp-gateway" is displayed.
		// https://inpsyde.atlassian.net/browse/PCP-
		title: 'PCP-0000 | Vaulting - Transaction - Checkout - PayPal - Save payment method',
		...orders.default,
		payment: {
			...payments.payPal,
			saveToAccount: true,
		},
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-3234
		title: 'PCP-3234 | Vaulting - Transaction - Checkout - ACDC - Save payment method',
		...orders.default,
		payment: {
			...payments.acdc,
			saveToAccount: true,
		},
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-
		title: 'PCP-0000 | Vaulting - Transaction - Checkout - ACDC - Do not save payment method',
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
		// https://inpsyde.atlassian.net/browse/PCP-
		title: 'PCP-0000 | Vaulting - Transaction - Checkout - ACDC - Pay with card other then saved and do not save it',
		...orders.default,
		payment: {
			...payments.acdc,
			saveToAccount: false,
		},
		customer,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-
		title: 'PCP-0000 | Vaulting - Transaction - Checkout - ACDC - Pay with card other then saved and save it',
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
		// FAIL: vaulted PayPal button is not displayed.
		// https://inpsyde.atlassian.net/browse/PCP-2051
		title: 'PCP-2051 | Vaulting - Transaction - Checkout - PayPal - Pay with vaulted account',
		...orders.default,
		payment: {
			...payments.payPal,
			isVaulted: true,
		},
		customer,
	},
	{
		// FAIL: payment is not accepted. Errors in console. https://inpsyde.atlassian.net/browse/PCP-5059
		// https://inpsyde.atlassian.net/browse/PCP-3235
		title: 'PCP-3235 | Vaulting - Transaction - Checkout - ACDC - Pay with saved card',
		...orders.default,
		payment: {
			...payments.acdc,
			isVaulted: true,
		},
		customer,
	},
];

export const vaultingCheckout = {
	savePaymentMethodData,
	acdcAdditionalCardData,
	vaultedPaymentMethodData,
};
