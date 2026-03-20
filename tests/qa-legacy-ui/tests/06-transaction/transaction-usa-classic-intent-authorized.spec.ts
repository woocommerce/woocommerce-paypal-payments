/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import {
	transactionsOnClassicCart,
	transactionsOnClassicCheckout
} from './_test-scenarios';
import {
	orders,
	payPal,
} from '../../resources';
import {
	payLaterClassicCartIntentAuthorized,
	payLaterClassicCheckoutIntentAuthorized
} from './_test-data/pay-later';
import {
	payPalClassicCartIntentAuthorized,
	payPalClassicCheckoutIntentAuthorized
} from './_test-data/paypal';

transactionsOnClassicCart( payPalClassicCartIntentAuthorized );
transactionsOnClassicCart( payLaterClassicCartIntentAuthorized );

transactionsOnClassicCheckout( payPalClassicCheckoutIntentAuthorized );
transactionsOnClassicCheckout( payLaterClassicCheckoutIntentAuthorized );

test( 'PCP-2164 | Transaction - Classic Cart - PayPal - Intent = Authorize - No package tracking in order', async ( {
	wooCommerceUtils,
	classicCart,
	classicCheckout,
	orderReceived,
	wooCommerceOrderEdit,
	utils,
} ) => {
	const tested = {
		...orders.default,
		payment: {
			...payPal,
			isAuthorized: true,
		},
	};

	await wooCommerceUtils.setTaxes( tested.taxes );
	await utils.fillVisitorsCart( tested.products );

	await classicCart.makeOrder( tested );
	await classicCheckout.fillCheckoutForm( tested.customer );
	await classicCheckout.placeOrder();
	// Expect Order Received page to be loaded
	await orderReceived.page.waitForURL( /order-received/ );
	await expect( orderReceived.heading() ).toBeVisible();
	const orderId = await orderReceived.getOrderNumber();
	await wooCommerceOrderEdit.visit( orderId );
	await expect(
		wooCommerceOrderEdit.payPalPackageTrackingSection()
	).not.toBeVisible();
} );
