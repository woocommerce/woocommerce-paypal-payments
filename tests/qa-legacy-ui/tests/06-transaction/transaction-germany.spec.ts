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
	storeConfigDefault,
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

test.beforeAll( async ( { utils } ) => {
	test.setTimeout( 3 * 60 * 1000 );
	await utils.configureStore( storeConfigDefault );
	await utils.configurePcp( pcpConfigGermany );
	await utils.pcpPaymentMethodIsEnabled( payPal.method );
	await utils.pcpPaymentMethodIsEnabled( payLater.method );
	await utils.pcpPaymentMethodIsEnabled( acdc.method );
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
		async ( { utils } ) => await utils.activateWpDebuggingPlugin()
	);

	transactionsOnPayByLink( payPalPayByLinkDebugging );

	test.beforeAll(
		async ( { utils } ) => await utils.deactivateWpDebuggingPlugin()
	);
} );
