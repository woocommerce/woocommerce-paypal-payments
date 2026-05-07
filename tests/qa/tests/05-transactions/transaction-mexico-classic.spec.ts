/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	merchants,
	storeConfigMexico,
	gateways,
	taxSettings,
	customers,
} from '../../resources';
import {
	transactionsOnClassicCheckout,
	transactionsOnClassicCheckoutOxxo,
} from './_test-scenarios';
import {
	bcdcClassicCheckout,
	bcdcClassicCheckoutExcludingTax,
	bcdcClassicCheckoutIntentAuthorized,
} from './_test-data/bcdc';
import { oxxoClassicCheckout } from './_test-data/oxxo';

/**
 * BCDC
 * BCDC is classic-checkout only — block checkout is not supported.
 */

const { payPal, bcdc, oxxo } = gateways;

test.beforeAll( async ( { utils, pcpApi, wooCommerceApi } ) => {
	await utils.configureStore( {
		...storeConfigMexico,
		enableClassicPages: true,
		customer: customers.mexico,
	} );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.mexico.client_id,
		merchants.mexico.client_secret
	);
	await pcpApi.updatePcpPaymentMethods( {
		[ payPal.id ]: { id: payPal.id, enabled: true },
		[ bcdc.id ]: { id: bcdc.id, enabled: true },
		[ oxxo.id ]: { id: oxxo.id, enabled: true },
	} );
	await wooCommerceApi.deleteAllOrders();
} );

for ( const testOrder of bcdcClassicCheckout ) {
	transactionsOnClassicCheckout( testOrder );
}

// Excluding Tax
test.describe( () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	for ( const testOrder of bcdcClassicCheckoutExcludingTax ) {
		transactionsOnClassicCheckout( testOrder );
	}

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );

// Intent Authorized
test.describe( () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { authorizeOnly: true } );
	} );

	for ( const testOrder of bcdcClassicCheckoutIntentAuthorized ) {
		transactionsOnClassicCheckout( testOrder );
	}

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { authorizeOnly: false } );
	} );
} );

/**
 * OXXO — Mexico cash payment via classic checkout
 */
for ( const testOrder of oxxoClassicCheckout ) {
	transactionsOnClassicCheckoutOxxo( testOrder );
}
