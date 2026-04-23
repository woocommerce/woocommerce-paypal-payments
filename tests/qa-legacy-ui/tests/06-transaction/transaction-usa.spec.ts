/**
 * Internal dependencies
 */
import { test } from '../../utils';
/**
 * External dependencies
 */
import {
	acdc,
	payLater,
	payPal,
	payUponInvoice,
	pcpConfigGermany,
	pcpConfigUsa,
	storeConfigDefault,
	storeConfigUsa,
	taxSettings,
} from '../../resources';
import {
	transactionsOnCart,
	transactionsOnCheckout,
	transactionsOnPayByLink,
	transactionsOnProduct,
} from './_test-scenarios';
import {
	payPalCart,
	payPalCartIntentAuthorized,
	payPalCheckout,
	payPalCheckoutIntentAuthorized,
	payPalPayByLink,
	payPalPayByLinkDebugging,
	payPalProduct,
	payPalProductVerticalButton,
	payPalCartExcludingTax,
	payPalCheckoutExcludingTax,
} from './_test-data/paypal';
import {
	payLaterCart,
	payLaterCartIntentAuthorized,
	payLaterCheckout,
	payLaterCheckoutIntentAuthorized,
	payLaterProduct,
	payLaterProductVerticalButton,
	payLaterCartExcludingTax,
	payLaterCheckoutExcludingTax,
} from './_test-data/pay-later';
import { acdcPayByLink } from './_test-data/acdc';

test.beforeAll( async ( { utils, standardPayments } ) => {
	test.setTimeout( 3 * 60 * 1000 );
	await utils.configureStore( storeConfigUsa );
	await utils.configurePcp( pcpConfigUsa );
	await utils.pcpPaymentMethodIsEnabled( payPal.method );
	await utils.pcpPaymentMethodIsEnabled( payLater.method );
	await utils.pcpPaymentMethodIsEnabled( acdc.method );
	await standardPayments.setup( {
		disableAlternativePaymentMethods: [ 'Venmo' ],
	} );
} );

transactionsOnCart( payPalCart );
transactionsOnCart( payLaterCart );

transactionsOnCheckout( payPalCheckout );
transactionsOnCheckout( payLaterCheckout );

transactionsOnProduct( payPalProduct );
transactionsOnProduct( payLaterProduct );

transactionsOnPayByLink( payPalPayByLink );
transactionsOnPayByLink( acdcPayByLink );

test.describe( 'Excluding Tax', () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	transactionsOnCart( payPalCartExcludingTax );
	transactionsOnCart( payLaterCartExcludingTax );

	transactionsOnCheckout( payPalCheckoutExcludingTax );
	transactionsOnCheckout( payLaterCheckoutExcludingTax );

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );

test.describe( 'Intent Authorized', () => {
	test.beforeAll( async ( { standardPayments } ) => {
		await standardPayments.setup( { intent: 'Authorize' } );
	} );

	transactionsOnCart( payPalCartIntentAuthorized );
	transactionsOnCart( payLaterCartIntentAuthorized );
	transactionsOnCheckout( payPalCheckoutIntentAuthorized );
	transactionsOnCheckout( payLaterCheckoutIntentAuthorized );

	test.afterAll( async ( { standardPayments } ) => {
		await standardPayments.setup( { intent: 'Capture' } );
	} );
} );

test.describe( 'Vertical buttons', () => {
	test.beforeAll( async ( { standardPayments } ) => {
		await standardPayments.setup( {
			singleProductButtonLayout: 'Vertical',
		} );
	} );

	transactionsOnProduct( payPalProductVerticalButton );
	transactionsOnProduct( payLaterProductVerticalButton );

	test.afterAll( async ( { standardPayments } ) => {
		await standardPayments.setup( {
			singleProductButtonLayout: 'Horizontal',
		} );
	} );
} );

test.describe( 'WP Debugging', () => {
	test.beforeAll(
		async ( { cli } ) => await cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } )
	);

	transactionsOnPayByLink( payPalPayByLinkDebugging );

	test.beforeAll(
		async ( { cli } ) => await cli.setWpConst( { WP_DEBUG: false, SCRIPT_DEBUG: false } )
	);
} );
