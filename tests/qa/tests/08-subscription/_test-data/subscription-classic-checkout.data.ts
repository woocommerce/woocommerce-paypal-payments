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
		// https://inpsyde.atlassian.net/browse/PCP-1498
		title: 'PCP-1498 | Vaulting subscription - Transaction - Classic checkout - PayPal - Order by guest @Critical',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer: guest,
		products: [ products.subscription100 ],
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-2944
		title: 'PCP-2944 | Vaulting subscription - Transaction - Classic checkout - PayPal - Free trial order by guest',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer: guest,
		products: [ products.subscriptionFreeTrial ],
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-2504
		title: 'PCP-2504 | Vaulting subscription - Transaction - Classic checkout - ACDC - Order by guest @Critical',
		...orders.default,
		payment: payments.acdc,
		merchant,
		customer: guest,
		products: [ products.subscription100 ],
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-2947
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
		// https://inpsyde.atlassian.net/browse/PCP-2889
		title: 'PCP-2889 | Vaulting subscription - Transaction - Classic checkout - PayPal - Order by customer @Critical',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer,
		products: [ products.subscription100 ],
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-2945
		title: 'PCP-2945 | Vaulting subscription - Transaction - Classic checkout - PayPal - Free trial order by customer',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer,
		products: [ products.subscriptionFreeTrial ],
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-4892
		title: 'PCP-4892 | Vaulting subscription - Transaction - Classic checkout - ACDC - Order by customer @Critical @Smoke',
		...orders.default,
		payment: payments.acdc,
		merchant,
		customer,
		products: [ products.subscription100 ],
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-2948
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
		// https://inpsyde.atlassian.net/browse/PCP-2531
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
		// https://inpsyde.atlassian.net/browse/PCP-4893
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
		// https://inpsyde.atlassian.net/browse/PCP-2642
		title: 'PCP-2642 | PayPal subscription - Transaction - Classic checkout - Order by customer @Critical @Smoke',
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
		// https://inpsyde.atlassian.net/browse/PCP-4894
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
