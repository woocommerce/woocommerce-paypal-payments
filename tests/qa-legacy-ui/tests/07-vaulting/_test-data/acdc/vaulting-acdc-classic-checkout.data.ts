/**
 * Internal dependencies
 */
import { orders, acdc } from '../../../../resources';

export const vaultingAcdcClassicCheckoutReguar = [
	{
		title: 'PCP-1204 | Vaulting - Transaction - Classic checkout - ACDC - Order with checked save card checkbox ',
		...orders.byCustomer,
		payment: {
			...acdc,
			saveToAccount: true,
		},
	},
	{
		title: 'PCP-2477 | Vaulting - Transaction - Classic checkout - ACDC - New customer without saved payment method',
		...orders.byCustomer, // SIARHEI-TODO: use different customer
		payment: acdc,
	},
];

export const vaultingAcdcClassicCheckoutVaulted = [
	{
		title: 'PCP-1206 | Vaulting - Transaction - Classic checkout - ACDC - Order paid with saved card', // bug PCP-3055
		...orders.byCustomer,
		payment: {
			...acdc,
			isVaulted: true,
		},
	},
];
