/**
 * Internal dependencies
 */
import {
	payments,
	orders,
	customers,
	guests,
	merchants,
	ShopOrder,
} from '../../../../resources';

const customer = customers.mexico;
const guest = guests.mexico;

/**
 * BCDC (Branded Card Debit/Credit) — classic checkout only.
 * Block checkout is NOT supported).
 */
const merchant = merchants.mexico;

export const bcdcClassicCheckout: ShopOrder[] = [
	{
		title: 'PCP-1211 | Transaction - Classic checkout - BCDC - Default order @Critical @Smoke',
		...orders.default,
		payment: payments.bcdc,
		customer: guest,
		merchant,
	},
	{
		title: 'PCP-2747 | Transaction - Classic checkout - BCDC - Order by customer',
		...orders.default,
		payment: payments.bcdc,
		customer,
		merchant,
	},
];

export const bcdcClassicCheckoutExcludingTax: ShopOrder[] = [
	{
		title: 'PCP-1253 | Transaction - Classic checkout - BCDC - Order with price excluding tax',
		...orders.excludingTax,
		payment: payments.bcdc,
		customer: guest,
		merchant,
	},
];

export const bcdcClassicCheckoutIntentAuthorized: ShopOrder[] = [
	{
		title: 'PCP-2759 | Transaction - Classic checkout - BCDC - Order with Intent Authorized',
		...orders.default,
		payment: {
			...payments.bcdc,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
		customer: guest,
		merchant,
	},
];
