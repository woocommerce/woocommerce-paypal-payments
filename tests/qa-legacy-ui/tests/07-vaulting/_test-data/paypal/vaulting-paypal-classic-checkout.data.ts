/**
 * Internal dependencies
 */
import { orders, payPal, payPalVaulted } from '../../../../resources';

export const vaultingPayPalClassicCheckoutRegular = [
	{
		title: 'PCP-1045 | Vaulting - Transaction - Classic checkout - PayPal - Default order with enabled vaulting',
		...orders.default,
		payment: payPal,
	},
];

export const vaultingPayPalClassicCheckoutNotVaulted = [
	{
		title: 'PCP-1380 | Vaulting - Transaction - Classic checkout - PayPal - Order paid with account other than saved',
		...orders.byCustomer,
		payment: {
			...payPal,
			// SIARHEI-TODO provide different account
			useNotVaultedAccount: {
				email: '',
				password: '',
			},
		},
	},
];

export const vaultingPayPalClassicCheckoutVaulted = [
	{
		title: 'PCP-1378 | Vaulting - Transaction - Classic checkout - PayPal - Order paid with saved payment method',
		...orders.byCustomer,
		payment: payPalVaulted,
	},
];
