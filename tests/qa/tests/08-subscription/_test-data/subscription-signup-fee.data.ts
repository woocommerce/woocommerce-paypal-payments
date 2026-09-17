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
		title: 'PCP-6959 | Vaulting subscription - Transaction - Checkout - PayPal - Sign-up fee order by guest',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer: guest,
		products: [ products.subscriptionSignUpFee ],
	},
];

const vaultingCustomer: ShopOrder[] = [
	{
		title: 'PCP-0000 | Vaulting subscription - Transaction - Checkout - PayPal - Sign-up fee order by customer',
		...orders.default,
		payment: payments.payPal,
		merchant,
		customer,
		products: [ products.subscriptionSignUpFee ],
	},
];

const payPalGuest: ShopOrder[] = [
	{
		title: 'PCP-6960 | PayPal subscription - Transaction - Checkout - Sign-up fee order by guest',
		...orders.default,
		payment: {
			...payments.payPal,
			saveToAccount: false, // with vaulting OFF - should not be saved as customers PM
			isPayPalSubscription: true,
		},
		merchant,
		customer: guest,
		products: [ products.subscriptionPayPalSignUpFee ],
	},
];

const payPalCustomer: ShopOrder[] = [
	{
		title: 'PCP-0000 | PayPal subscription - Transaction - Checkout - Sign-up fee order by customer',
		...orders.default,
		payment: {
			...payments.payPal,
			saveToAccount: false, // with vaulting OFF - should not be saved as customers PM
			isPayPalSubscription: true,
		},
		merchant,
		customer,
		products: [ products.subscriptionPayPalSignUpFee ],
	},
];

export const subscriptionSignUpFee = {
	vaultingGuest,
	vaultingCustomer,
	payPalGuest,
	payPalCustomer,
};
