/**
 * Internal dependencies
 */
import {
	acdc,
	payLater,
	payPal,
	pcpConfigUsa,
	storeConfigUsa,
	taxSettings
} from '../../resources';
import { test } from '../../utils';
import {
	acdcClassicCheckout,
	acdcClassicCheckoutDebugging,
	acdcClassicCheckoutExcludingTax
} from './_test-data/acdc';
import {
	payLaterClassicCart,
	payLaterClassicCartExcludingTax,
	payLaterClassicCheckout,
	payLaterClassicCheckoutExcludingTax,
	payLaterClassicProduct
} from './_test-data/pay-later';
import {
	payPalClassicCart,
	payPalClassicCartExcludingTax,
	payPalClassicCheckout,
	payPalClassicCheckoutExcludingTax,
	payPalClassicProduct
} from './_test-data/paypal';
import {
	transactionsOnClassicCart,
	transactionsOnClassicCheckout,
	transactionsOnClassicProduct,
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
			}
		} );
		await utils.pcpPaymentMethodIsEnabled( payPal.method );
		await utils.pcpPaymentMethodIsEnabled( payLater.method );
		await utils.pcpPaymentMethodIsEnabled( acdc.method );
		await utils.updatePcpPlugin();
	} );

	transactionsOnClassicCart( payPalClassicCart );
	transactionsOnClassicCart( payLaterClassicCart );

	transactionsOnClassicCheckout( payPalClassicCheckout );
	transactionsOnClassicCheckout( payLaterClassicCheckout );
	transactionsOnClassicCheckout( acdcClassicCheckout );

	transactionsOnClassicProduct( payPalClassicProduct );
	transactionsOnClassicProduct( payLaterClassicProduct );

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

	test.describe( 'WP Debugging', () => {
		test.beforeAll(
			async ( { cli } ) => await cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } )
		);

		transactionsOnClassicCheckout( acdcClassicCheckoutDebugging );

		test.afterAll(
			async ( { cli } ) => await cli.setWpConst( { WP_DEBUG: false, SCRIPT_DEBUG: false } )
		);
	} );
} );
