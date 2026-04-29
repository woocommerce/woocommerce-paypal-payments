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
		// https://inpsyde.atlassian.net/browse/PCP-1211
		title: 'PCP-1211 | Transaction - Classic checkout - BCDC - Default order @Critical',
		...orders.default,
		payment: payments.standardCardButton,
		customer: guest,
		merchant,
	},
	{
		// https://inpsyde.atlassian.net/browse/PCP-2747
		title: 'PCP-2747 | Transaction - Classic checkout - BCDC - Order by customer',
		...orders.default,
		payment: payments.standardCardButton,
		customer,
		merchant,
	},
];

export const bcdcClassicCheckoutExcludingTax: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-1253
		title: 'PCP-1253 | Transaction - Classic checkout - BCDC - Order with price excluding tax',
		...orders.excludingTax,
		payment: payments.standardCardButton,
		customer: guest,
		merchant,
	},
];

export const bcdcClassicCheckoutIntentAuthorized: ShopOrder[] = [
	{
		// https://inpsyde.atlassian.net/browse/PCP-2759
		title: 'PCP-2759 | Transaction - Classic checkout - BCDC - Order with Intent Authorized',
		...orders.default,
		payment: {
			...payments.standardCardButton,
			isAuthorized: true,
		},
		orderStatus: 'on-hold',
		customer: guest,
		merchant,
	},
];
