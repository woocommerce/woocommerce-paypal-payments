/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import {
	acdc,
	debitOrCreditCard,
	orders,
	payLater,
	payPal,
	pcpConfigUsa,
	standardCardButton,
	storeConfigUsa,
	taxSettings,
	venmo,
} from '../../resources';
import {
	transactionsOnClassicCart,
	transactionsOnClassicCheckout,
	transactionsOnClassicProduct,
} from './_test-scenarios';
import {
	venmoClassicCartUsa,
	venmoClassicCheckoutUsa,
	venmoClassicProductUsa,
	venmoClassicCheckoutUsaExcludingTax,
} from './_test-data/venmo';
import {
	payPalClassicCart,
	payPalClassicCartExcludingTax,
	payPalClassicCartHorizontalButton,
	payPalClassicCartIntentAuthorized,
	payPalClassicCheckout,
	payPalClassicCheckoutExcludingTax,
	payPalClassicCheckoutHorizontalButton,
	payPalClassicCheckoutIntentAuthorized,
	payPalClassicCheckoutSpecificMerchant,
	payPalClassicProduct,
	payPalClassicProductVerticalButton,
	specificMerchant,
} from './_test-data/paypal';
import {
	payLaterClassicCart,
	payLaterClassicCartExcludingTax,
	payLaterClassicCartHorizontalButton,
	payLaterClassicCartIntentAuthorized,
	payLaterClassicCheckout,
	payLaterClassicCheckoutExcludingTax,
	payLaterClassicCheckoutHorizontalButton,
	payLaterClassicCheckoutIntentAuthorized,
	payLaterClassicProduct,
	payLaterClassicProductVerticalButton,
} from './_test-data/pay-later';
import {
	acdcClassicCheckout,
	acdcClassicCheckout3ds,
	acdcClassicCheckoutDebugging,
	acdcClassicCheckoutExcludingTax,
} from './_test-data/acdc';
import {
	debitOrCreditCardClassicCheckout,
	debitOrCreditCardClassicCheckoutExcludingTax,
	debitOrCreditCardClassicCheckoutIntentAuthorized,
} from './_test-data/debit-or-credit-card';
import {
	standardCardButtonClassicCheckout,
	standardCardButtonClassicCheckoutExcludingTax,
	standardCardButtonClassicCheckoutIntentAuthorized,
} from './_test-data/standard-card-button';

test.beforeAll( async ( { utils, standardPayments } ) => {
	test.setTimeout( 3 * 60 * 1000 );
	await utils.configureStore( {
		...storeConfigUsa,
		classicPages: true,
	} );
	await utils.configurePcp( pcpConfigUsa );
	await utils.pcpPaymentMethodIsEnabled( payPal.method );
	await utils.pcpPaymentMethodIsEnabled( payLater.method );
	await utils.pcpPaymentMethodIsEnabled( acdc.method );
	await standardPayments.setup( {
		disableAlternativePaymentMethods: [ 'Venmo' ],
	} );
} );

transactionsOnClassicCart( payPalClassicCart );
transactionsOnClassicCart( payLaterClassicCart );

transactionsOnClassicCheckout( payPalClassicCheckout );
transactionsOnClassicCheckout( payLaterClassicCheckout );
transactionsOnClassicCheckout( acdcClassicCheckout );

transactionsOnClassicProduct( payPalClassicProduct );
transactionsOnClassicProduct( payLaterClassicProduct );

// test.describe( 'Venmo', () => {
// 	test.beforeAll( async ( { utils } ) => {
// 		await utils.pcpPaymentMethodIsEnabled( venmo.method );
// 	} );

// 	transactionsOnClassicCart( venmoClassicCartUsa );
// 	transactionsOnClassicCheckout( venmoClassicCheckoutUsa );
// 	transactionsOnClassicProduct( venmoClassicProductUsa );

// 	test.afterAll( async ( { standardPayments } ) => {
// 		await standardPayments.setup( {
// 			disableAlternativePaymentMethods: [ 'Venmo' ],
// 		} );
// 	} );
// } );

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
		async ( { cli } ) => await cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } )
	);

	transactionsOnClassicCheckout( acdcClassicCheckoutDebugging );

	test.afterAll(
		async ( { cli } ) => await cli.setWpConst( { WP_DEBUG: false, SCRIPT_DEBUG: false } )
	);
} );

test.describe( 'Specific merchants', () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.connectMerchant( specificMerchant );
	} );

	transactionsOnClassicCheckout( payPalClassicCheckoutSpecificMerchant );

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.connectMerchant( pcpConfigUsa.merchant );
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
