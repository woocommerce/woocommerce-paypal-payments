/**
 * Internal dependencies
 */
import {
	customers,
	merchants,
	orders,
	payments,
	products,
	ShopOrder,
} from '../../../resources';

const customer = customers.usa;
const merchant = merchants.usa;
const currency = process.env.WC_DEFAULT_CURRENCY;

const vaultingRenewal: ShopOrder[] = [
	{
		title: 'PCP-2505 | Vaulting subscription - PayPal - Order renewal @Critical @Smoke',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer,
		products: [ products.subscription100 ],
		currency,
	},
	{
		title: 'PCP-2514 | Vaulting subscription - ACDC - Order renewal @Critical @Smoke',
		...orders.default,
		payment: payments.acdc,
		merchant,
		customer,
		products: [ products.subscription100 ],
		currency,
	},
];

const vaultingFreeTrialRenewal: ShopOrder[] = [
	{
		title: 'PCP-4913 | Vaulting subscription - PayPal - Free trial order renewal',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer,
		products: [ products.subscriptionFreeTrial ],
		currency,
	},
	{
		title: 'PCP-4914 | Vaulting subscription - ACDC - Free trial order renewal',
		...orders.default,
		payment: payments.acdc,
		merchant,
		customer,
		products: [ products.subscriptionFreeTrial ],
		currency,
	},
];

const payPalRenewal: ShopOrder[] = [
	{
		title: 'PCP-2048 | PayPal subscription - Order renewal @Critical',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer,
		products: [ products.subscriptionPayPal ],
		currency,
	},
];

const payPalFreeTrialRenewal: ShopOrder[] = [
	{
		title: 'PCP-4915 | PayPal subscription - Free trial order renewal',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer,
		products: [ products.subscriptionPayPalFreeTrial ],
		currency,
	},
];

export const subscriptionRenewal = {
	vaultingRenewal,
	vaultingFreeTrialRenewal,
	payPalRenewal,
	payPalFreeTrialRenewal,
};
