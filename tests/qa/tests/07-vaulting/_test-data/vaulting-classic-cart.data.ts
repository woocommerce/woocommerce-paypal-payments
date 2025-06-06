/**
 * Internal dependencies
 */
import { customers, orders, payments, ShopOrder } from '../../../resources';

const customer = customers.usa;

const savePaymentMethodData: ShopOrder[] = [
	{
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
