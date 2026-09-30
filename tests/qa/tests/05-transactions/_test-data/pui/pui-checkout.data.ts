/**
 * Internal dependencies
 */
import {
	payments,
	orders,
	customers,
	guests,
	products,
	ShopOrder,
} from '../../../../resources';

const customer = customers.germany;
const guest = guests.germany;
const currency = 'EUR';
const { pui } = payments;

// Distinct totals per PUI test: PayPal rejects identical PUI orders placed back to back (PUI_DUPLICATE_ORDER).
export const puiCheckout: ShopOrder[] = [
	{
		title: 'PCP-6640 | Transaction - Checkout - Pay upon Invoice - Germany - Guest - Default order @Critical',
		...orders.default,
		products: [ products.simple100, products.simple100 ],
		payment: pui,
		customer: guest,
		currency,
	},
	{
		title: 'PCP-6641 | Transaction - Checkout - Pay upon Invoice - Germany - Customer - Default order @Critical @Smoke',
		...orders.default,
		products: [ products.simple100, products.simple10, products.simple10 ],
		payment: pui,
		customer,
		currency,
	},
];
