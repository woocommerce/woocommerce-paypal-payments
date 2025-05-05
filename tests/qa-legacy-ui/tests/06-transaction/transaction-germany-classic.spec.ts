/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
/**
 * External dependencies
 */
import {
	orders,
	acdc,
	acdc3ds,
	payLater,
	payPal,
	payUponInvoice,
	pcpConfigGermany,
	standardCardButton,
	debitOrCreditCard,
	storeConfigClassic,
	taxSettings,
} from '../../resources';
import {
	transactionsOnClassicCart,
	transactionsOnClassicCheckout,
	transactionsOnClassicProduct,
} from './_test-scenarios';
import {
	payPalClassicCart,
	payPalClassicCartHorizontalButton,
	payPalClassicCartIntentAuthorized,
	payPalClassicCheckout,
	payPalClassicCheckoutHorizontalButton,
	payPalClassicCheckoutIntentAuthorized,
	payPalClassicCheckoutSpecificMerchant,
	payPalClassicProduct,
	payPalClassicProductVerticalButton,
	specificMerchant,
	payPalClassicCartExcludingTax,
	payPalClassicCheckoutExcludingTax,
} from './_test-data/paypal';
import {
	payLaterClassicCart,
	payLaterClassicCartHorizontalButton,
	payLaterClassicCartIntentAuthorized,
	payLaterClassicCheckout,
	payLaterClassicCheckoutHorizontalButton,
	payLaterClassicCheckoutIntentAuthorized,
	payLaterClassicProductVerticalButton,
	payLaterClassicProduct,
	payLaterClassicCartExcludingTax,
	payLaterClassicCheckoutExcludingTax,
} from './_test-data/pay-later';
import {
	payUponInvoiceClassicCheckoutGermany,
	payUponInvoiceClassicCheckoutGermanyExcludingTax,
} from './_test-data/pay-upon-invoice';
import {
	acdcClassicCheckout,
	acdcClassicCheckoutDebugging,
	acdcClassicCheckoutExcludingTax,
	acdcClassicCheckout3ds,
} from './_test-data/acdc';
import {
	standardCardButtonClassicCheckout,
	standardCardButtonClassicCheckoutIntentAuthorized,
	standardCardButtonClassicCheckoutExcludingTax,
} from './_test-data/standard-card-button';
import {
	debitOrCreditCardClassicCheckout,
	debitOrCreditCardClassicCheckoutIntentAuthorized,
	debitOrCreditCardClassicCheckoutExcludingTax,
} from './_test-data/debit-or-credit-card';

test.beforeAll( async ( { utils } ) => {
	test.setTimeout( 3 * 60 * 1000 );
	await utils.configureStore( storeConfigClassic );
	await utils.configurePcp( pcpConfigGermany );
	await utils.pcpPaymentMethodIsEnabled( payPal.method );
	await utils.pcpPaymentMethodIsEnabled( payLater.method );
	await utils.pcpPaymentMethodIsEnabled( payUponInvoice.method );
	await utils.pcpPaymentMethodIsEnabled( acdc.method );
} );

transactionsOnClassicCart( payPalClassicCart );
transactionsOnClassicCart( payLaterClassicCart );

transactionsOnClassicCheckout( payPalClassicCheckout );
transactionsOnClassicCheckout( payLaterClassicCheckout );
transactionsOnClassicCheckout( payUponInvoiceClassicCheckoutGermany );
transactionsOnClassicCheckout( acdcClassicCheckout );

transactionsOnClassicProduct( payPalClassicProduct );
transactionsOnClassicProduct( payLaterClassicProduct );

test.describe( 'ACDC 3DS', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.advancedCardProcessing.setup( {
			threeDSecure: 'Always trigger 3D Secure',
		} );
	} );

	transactionsOnClassicCheckout( acdcClassicCheckout3ds );

	test.afterAll( async ( { utils } ) => {
		await utils.advancedCardProcessing.setup( {
			threeDSecure:
				'No 3D Secure (transaction will be denied if 3D Secure is required)',
		} );
	} );
} );

test.describe( 'Excluding Tax', () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	transactionsOnClassicCart( payPalClassicCartExcludingTax );
	transactionsOnClassicCart( payLaterClassicCartExcludingTax );

	transactionsOnClassicCheckout( payPalClassicCheckoutExcludingTax );
	transactionsOnClassicCheckout( payLaterClassicCheckoutExcludingTax );
	transactionsOnClassicCheckout(
		payUponInvoiceClassicCheckoutGermanyExcludingTax
	);
	transactionsOnClassicCheckout( acdcClassicCheckoutExcludingTax );

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );

test.describe( 'Intent Authorized', () => {
	test.beforeAll( async ( { standardPayments } ) => {
		await standardPayments.setup( { intent: 'Authorize' } );
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

	test.afterAll( async ( { standardPayments } ) => {
		await standardPayments.setup( { intent: 'Capture' } );
	} );
} );

test.describe( 'Button orientation', () => {
	test.beforeAll( async ( { standardPayments } ) => {
		await standardPayments.setup( {
			classicCartButtonLayout: 'Horizontal',
			classicCheckoutButtonLayout: 'Horizontal',
			singleProductButtonLayout: 'Vertical',
		} );
	} );

	transactionsOnClassicCart( payPalClassicCartHorizontalButton );
	transactionsOnClassicCart( payLaterClassicCartHorizontalButton );

	transactionsOnClassicCheckout( payPalClassicCheckoutHorizontalButton );
	transactionsOnClassicCheckout( payLaterClassicCheckoutHorizontalButton );

	transactionsOnClassicProduct( payPalClassicProductVerticalButton );
	transactionsOnClassicProduct( payLaterClassicProductVerticalButton );

	test.afterAll( async ( { standardPayments } ) => {
		await standardPayments.setup( {
			classicCartButtonLayout: 'Vertical',
			classicCheckoutButtonLayout: 'Vertical',
			singleProductButtonLayout: 'Horizontal',
		} );
	} );
} );

test.describe( 'WP Debugging', () => {
	test.beforeAll(
		async ( { utils } ) => await utils.activateWpDebuggingPlugin()
	);

	transactionsOnClassicCheckout( acdcClassicCheckoutDebugging );

	test.afterAll(
		async ( { utils } ) => await utils.deactivateWpDebuggingPlugin()
	);
} );

test.describe( 'Specific merchants', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.connectMerchant( specificMerchant );
	} );

	transactionsOnClassicCheckout( payPalClassicCheckoutSpecificMerchant );

	test.afterAll( async ( { utils } ) => {
		await utils.connectMerchant( pcpConfigGermany.merchant );
	} );
} );

test.describe( 'Standard Card Button', () => {
	test.beforeAll(
		async ( { utils } ) =>
			await utils.pcpPaymentMethodIsEnabled( standardCardButton.method )
	);

	transactionsOnClassicCheckout( standardCardButtonClassicCheckout );

	test.describe( 'Excluding Tax', () => {
		test.beforeAll( async ( { wooCommerceUtils } ) => {
			await wooCommerceUtils.setTaxes( taxSettings.excluding );
		} );

		transactionsOnClassicCheckout(
			standardCardButtonClassicCheckoutExcludingTax
		);

		test.afterAll( async ( { wooCommerceUtils } ) => {
			await wooCommerceUtils.setTaxes( taxSettings.including );
		} );
	} );

	test.describe( 'Intent Authorized', () => {
		test.beforeAll(
			async ( { standardPayments } ) =>
				await standardPayments.setup( { intent: 'Authorize' } )
		);

		transactionsOnClassicCheckout(
			standardCardButtonClassicCheckoutIntentAuthorized
		);

		test.afterAll(
			async ( { standardPayments } ) =>
				await standardPayments.setup( { intent: 'Capture' } )
		);
	} );

	test.afterAll(
		async ( { utils } ) =>
			await utils.pcpPaymentMethodIsEnabled( acdc.method )
	);
} );

test.describe( 'Debit or Credit Card', () => {
	test.beforeAll(
		async ( { utils } ) =>
			await utils.pcpPaymentMethodIsEnabled( debitOrCreditCard.method )
	);

	transactionsOnClassicCheckout( debitOrCreditCardClassicCheckout );

	test.describe( 'Excluding Tax', () => {
		test.beforeAll( async ( { wooCommerceUtils } ) => {
			await wooCommerceUtils.setTaxes( taxSettings.excluding );
		} );

		transactionsOnClassicCheckout(
			debitOrCreditCardClassicCheckoutExcludingTax
		);

		test.afterAll( async ( { wooCommerceUtils } ) => {
			await wooCommerceUtils.setTaxes( taxSettings.including );
		} );
	} );

	test.describe( 'Intent Authorized', () => {
		test.beforeAll(
			async ( { standardPayments } ) =>
				await standardPayments.setup( { intent: 'Authorize' } )
		);

		transactionsOnClassicCheckout(
			debitOrCreditCardClassicCheckoutIntentAuthorized
		);

		test.afterAll(
			async ( { standardPayments } ) =>
				await standardPayments.setup( { intent: 'Capture' } )
		);
	} );

	test.afterAll(
		async ( { utils } ) =>
			await utils.pcpPaymentMethodIsEnabled( acdc.method )
	);
} );
