/**
 * Internal dependencies
 */
import { customers, orders, payments, ShopOrder } from '../../../resources';

const customer = customers.usa;

const savePaymentMethodData: ShopOrder[] = [
	{
		// FAIL: vaulted PayPal button is not displayed.
		// https://inpsyde.atlassian.net/browse/PCP-
		title: 'PCP-0000 | Vaulting - Transaction - Classic cart - PayPal - Save payment method',
		...orders.default,
		payment: {
			...payments.payPal,
			saveToAccount: true,
		},
		customer,
	},
];

const vaultedPaymentMethodData: ShopOrder[] = [
	{
		// FAIL: vaulted PayPal button is not displayed.
		// https://inpsyde.atlassian.net/browse/PCP-
		title: 'PCP-0000 | Vaulting - Transaction - Classic cart - PayPal - Pay with vaulted account',
		...orders.default,
		payment: {
			...payments.payPal,
			isVaulted: true,
		},
		customer,
	},
];

export const vaultingClassicCart = {
	savePaymentMethodData,
	vaultedPaymentMethodData,
};
