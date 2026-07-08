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
		title: 'PCP-4895 | Vaulting subscription - Transaction - Checkout - PayPal - Order by guest @Critical @Smoke',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer: guest,
		products: [ products.subscription100 ],
	},
	{
		title: 'PCP-4897 | Vaulting subscription - Transaction - Checkout - PayPal - Free trial order by guest',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer: guest,
		products: [ products.subscriptionFreeTrial ],
	},
	{
		title: 'PCP-4896 | Vaulting subscription - Transaction - Checkout - ACDC - Order by guest @Critical @Smoke',
		...orders.default,
		payment: payments.acdc,
		merchant,
		customer: guest,
		products: [ products.subscription100 ],
	},
	{
		title: 'PCP-3555 | Vaulting subscription - Transaction - Checkout - ACDC - Free trial order by guest',
		...orders.default,
		payment: payments.acdc,
		merchant,
		customer: guest,
		products: [ products.subscriptionFreeTrial ],
	},
];

const vaultingCustomer: ShopOrder[] = [
	{
		title: 'PCP-2570 | Vaulting subscription - Transaction - Checkout - PayPal - Order by customer @Critical',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer,
		products: [ products.subscription100 ],
	},
	{
		title: 'PCP-4899 | Vaulting subscription - Transaction - Checkout - PayPal - Free trial order by customer',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer,
		products: [ products.subscriptionFreeTrial ],
	},
	{
		title: 'PCP-4898 | Vaulting subscription - Transaction - Checkout - ACDC - Order by customer @Critical',
		...orders.default,
		payment: payments.acdc,
		merchant,
		customer,
		products: [ products.subscription100 ],
	},
	{
		title: 'PCP-3554 | Vaulting subscription - Transaction - Checkout - ACDC - Free trial order by customer',
		...orders.default,
		payment: payments.acdc,
		merchant,
		customer,
		products: [ products.subscriptionFreeTrial ],
	},
];

const payPalGuest: ShopOrder[] = [
	{
		title: 'PCP-2574 | PayPal subscription - Transaction - Checkout - Order by guest @Critical',
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
		title: 'PCP-4900 | PayPal subscription - Transaction - Checkout - Free trial order by guest',
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
		title: 'PCP-2576 | PayPal subscription - Transaction - Checkout - Order by customer @Critical',
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
		title: 'PCP-4901 | PayPal subscription - Transaction - Checkout - Free trial order by customer',
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

export const subscriptionCheckout = {
	vaultingGuest,
	vaultingCustomer,
	payPalGuest,
	payPalCustomer,
};
