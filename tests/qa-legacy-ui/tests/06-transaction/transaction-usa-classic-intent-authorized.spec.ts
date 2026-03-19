/**
 * Internal dependencies
 */
import {
	acdc,
	orders,
	payLater,
	payPal,
	pcpConfigUsa,
	storeConfigUsa
} from '../../resources';
import { expect, test } from '../../utils';
import {
	payLaterClassicCartIntentAuthorized,
	payLaterClassicCheckoutIntentAuthorized
} from './_test-data/pay-later';
import {
	payPalClassicCartIntentAuthorized,
	payPalClassicCheckoutIntentAuthorized
} from './_test-data/paypal';
import {
	transactionsOnClassicCart,
	transactionsOnClassicCheckout
} from './_test-scenarios';

test.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
		test.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( {
			...storeConfigUsa,
			classicPages: true,
		} );
		await utils.configurePcp( {
			...pcpConfigUsa,
			standardPayments: {
				disableAlternativePaymentMethods: [ 'Venmo' ],
				intent: 'Authorize',
			}
		} );
		await utils.pcpPaymentMethodIsEnabled( payPal.method );
		await utils.pcpPaymentMethodIsEnabled( payLater.method );
		await utils.updatePcpPlugin();
	} );

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
} );
