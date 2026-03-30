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
		// https://inpsyde.atlassian.net/browse/PCP-2505
		title: 'PCP-2505 | Vaulting subscription - PayPal - Order renewal @Critical @Smoke',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer,
		products: [ products.subscription100 ],
		currency,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-2514
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
		// https://inpsyde.atlassian.net/browse/PCP-4913
		title: 'PCP-4913 | Vaulting subscription - PayPal - Free trial order renewal',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer,
		products: [ products.subscriptionFreeTrial ],
		currency,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-4914
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
		// https://inpsyde.atlassian.net/browse/PCP-2048
		title: 'PCP-2048 | PayPal subscription - Order renewal @Critical @Smoke',
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
		// https://inpsyde.atlassian.net/browse/PCP-4915
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
