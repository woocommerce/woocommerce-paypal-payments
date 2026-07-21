/**
 * Internal dependencies
 */
import {
	customers,
	guests,
	merchants,
	orders,
	payments,
	products,
	ShopOrder,
} from '../../../resources';

const guest = guests.usa;
const customer = customers.usa;
const merchant = merchants.usa;

const vaultingGuest: ShopOrder[] = [
	{
		title: 'PCP-1498 | Vaulting subscription - Transaction - Classic checkout - PayPal - Order by guest @Critical @Smoke',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer: guest,
		products: [ products.subscription100 ],
	},
	{
		title: 'PCP-2944 | Vaulting subscription - Transaction - Classic checkout - PayPal - Free trial order by guest',
		...orders.default,
		payment: { ...payments.payPal, isFreeTrialSubscription: true },
		merchant,
		customer: guest,
		products: [ products.subscriptionFreeTrial ],
	},
	{
		title: 'PCP-2504 | Vaulting subscription - Transaction - Classic checkout - ACDC - Order by guest @Critical @Smoke',
		...orders.default,
		payment: payments.acdc,
		merchant,
		customer: guest,
		products: [ products.subscription100 ],
	},
	{
		title: 'PCP-2947 | Vaulting subscription - Transaction - Classic checkout - ACDC - Free trial order by guest',
		...orders.default,
		payment: payments.acdc,
		merchant,
		customer: guest,
		products: [ products.subscriptionFreeTrial ],
	},
];

const vaultingCustomer: ShopOrder[] = [
	{
		title: 'PCP-2889 | Vaulting subscription - Transaction - Classic checkout - PayPal - Order by customer @Critical',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer,
		products: [ products.subscription100 ],
	},
	{
		title: 'PCP-2945 | Vaulting subscription - Transaction - Classic checkout - PayPal - Free trial order by customer',
		...orders.default,
		payment: { ...payments.payPal, isFreeTrialSubscription: true },
		merchant,
		customer,
		products: [ products.subscriptionFreeTrial ],
	},
	{
		title: 'PCP-4892 | Vaulting subscription - Transaction - Classic checkout - ACDC - Order by customer @Critical',
		...orders.default,
		payment: payments.acdc,
		merchant,
		customer,
		products: [ products.subscription100 ],
	},
	{
		title: 'PCP-2948 | Vaulting subscription - Transaction - Classic checkout - ACDC - Free trial order by customer',
		...orders.default,
		payment: payments.acdc,
		merchant,
		customer,
		products: [ products.subscriptionFreeTrial ],
	},
];

const payPalGuest: ShopOrder[] = [
	{
		title: 'PCP-2531 | PayPal subscription - Transaction - Classic checkout - Order by guest @Critical',
		...orders.default,
		payment: {
			...payments.payPal,
			saveToAccount: false, // with vaulting OFF - should not be saved as customers PM
		},
		merchant,
		customer: guest,
		products: [ products.subscriptionPayPal ],
	},
	{
		title: 'PCP-4893 | PayPal subscription - Transaction - Classic checkout - Free trial order by guest',
		...orders.default,
		payment: {
			...payments.payPal,
			saveToAccount: false, // with vaulting OFF - should not be saved as customers PM
		},
		merchant,
		customer: guest,
		products: [ products.subscriptionPayPalFreeTrial ],
	},
];

const payPalCustomer: ShopOrder[] = [
	{
		title: 'PCP-2642 | PayPal subscription - Transaction - Classic checkout - Order by customer @Critical',
		...orders.default,
		payment: {
			...payments.payPal,
			saveToAccount: false, // with vaulting OFF - should not be saved as customers PM
		},
		merchant,
		customer,
		products: [ products.subscriptionPayPal ],
	},
	{
		title: 'PCP-4894 | PayPal subscription - Transaction - Classic checkout - Free trial order by customer',
		...orders.default,
		payment: {
			...payments.payPal,
			saveToAccount: false, // with vaulting OFF - should not be saved as customers PM
		},
		merchant,
		customer,
		products: [ products.subscriptionPayPalFreeTrial ],
	},
];

export const subscriptionClassicCheckout = {
	vaultingGuest,
	vaultingCustomer,
	payPalGuest,
	payPalCustomer,
};
