/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	merchants,
	storeConfigUsa,
	gateways,
	taxSettings
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
} from './_test-data/venmo';
import { payPalCheckoutExcludingTax, payPalClassicCheckout, payPalClassicCheckoutIntentAuthorized } from './_test-data/paypal';

const { payPal, venmo, fastlane } = gateways;

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore(  {
		...storeConfigUsa,
		classicPages: true,
	} );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
	await pcpApi.updatePcpPaymentMethods( {
		[ payPal.id ]: { id: payPal.id, enabled: true },
		[ venmo.id ]: { id: venmo.id, enabled: true },
		[ fastlane.id ]: { id: fastlane.id, enabled: false },
	} );
} );

transactionsOnClassicCheckout( payPalClassicCheckout );

/**
 * Venmo is eligible only for USA/USD
 */
// NOT TESTABLE AT THE MOMENT
// transactionsOnClassicCheckout( venmoClassicCheckoutUsa );
// transactionsOnClassicCart( venmoClassicCartUsa );
// transactionsOnClassicProduct( venmoClassicProductUsa );

test.describe( 'Excluding Tax', () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	transactionsOnClassicCheckout( payPalCheckoutExcludingTax );

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );

test.describe( 'Intent Authorized', () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { authorizeOnly: true } );
	} );

	transactionsOnClassicCheckout( payPalClassicCheckoutIntentAuthorized );

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { authorizeOnly: false } );
	} );
} );