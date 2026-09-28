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
		payment: payments.payPalSubscription,
		merchant,
		customer,
		products: [ products.subscription100 ],
		currency,
	},
	// Fails intermittently in CI until PCP-6991 is fixed: the renewal runs before
	// PayPal lists the new card, so the saved WC card token gets deleted.
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
		payment: { ...payments.payPalSubscription, isFreeTrialSubscription: true },
		merchant,
		customer,
		products: [ products.subscriptionFreeTrial ],
		currency,
	},
	// Fails intermittently in CI until PCP-6991 is fixed (same cause as PCP-2514).
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
		payment: {
			...payments.payPalSubscription,
			saveToAccount: false, // with vaulting OFF - should not be saved as customers PM
			isPayPalSubscription: true,
		},
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
		payment: {
			...payments.payPalSubscription,
			saveToAccount: false, // with vaulting OFF - should not be saved as customers PM
			isPayPalSubscription: true,
		},
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
