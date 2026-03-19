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
	pcpConfigUsa,
	storeConfigUsa,
	taxSettings
} from '../../resources';
import { acdcPayByLink } from './_test-data/acdc';
import {
	payLaterCart,
	payLaterCartExcludingTax,
	payLaterCheckout,
	payLaterCheckoutExcludingTax,
	payLaterProduct
} from './_test-data/pay-later';
import {
	payPalCart,
	payPalCartExcludingTax,
	payPalCheckout,
	payPalCheckoutExcludingTax,
	payPalPayByLink,
	payPalPayByLinkDebugging,
	payPalProduct
} from './_test-data/paypal';
import {
	transactionsOnCart,
	transactionsOnCheckout,
	transactionsOnPayByLink,
	transactionsOnProduct,
} from './_test-scenarios';

test.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

test.describe( () => {

	test.beforeAll( async ( { utils, standardPayments } ) => {
		test.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( storeConfigUsa );
		await utils.configurePcp( pcpConfigUsa );
		await utils.pcpPaymentMethodIsEnabled( payPal.method );
		await utils.pcpPaymentMethodIsEnabled( payLater.method );
		await utils.pcpPaymentMethodIsEnabled( acdc.method );
		await standardPayments.setup( {
			disableAlternativePaymentMethods: [ 'Venmo' ],
		} );
		await utils.updatePcpPlugin();
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

	test.describe( 'WP Debugging', () => {
		test.beforeAll(
			async ( { cli } ) => await cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } )
		);

		transactionsOnPayByLink( payPalPayByLinkDebugging );

		test.beforeAll(
			async ( { cli } ) => await cli.setWpConst( { WP_DEBUG: false, SCRIPT_DEBUG: false } )
		);
	} );
} );
