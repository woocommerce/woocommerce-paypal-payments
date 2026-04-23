/**
 * Internal dependencies
 */
import { customers, orders, payments, ShopOrder } from '../../../resources';

const customer = customers.usa;

const savePaymentMethodData: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-5397
		title: 'PCP-5397 | Vaulting - Transaction - Classic cart - PayPal - Save payment method @Critical @Smoke',
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
		// https://inpsyde.atlassian.net/browse/PCP-5398
		title: 'PCP-5398 | Vaulting - Transaction - Classic cart - PayPal - Pay with vaulted account @Critical',
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
