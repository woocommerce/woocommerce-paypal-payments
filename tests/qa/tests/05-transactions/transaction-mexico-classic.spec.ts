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
import { transactionsOnClassicCheckout } from './_test-scenarios';
import {
	standardCardButtonClassicCheckout,
	standardCardButtonClassicCheckoutExcludingTax,
	standardCardButtonClassicCheckoutIntentAuthorized,
} from './_test-data/standard-card-button';

/**
 * Standard Card Button / BCDC
 * BCDC is classic-checkout only — block checkout is not supported.
 */

const { payPal, standardCardButton } = gateways;

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
		[ standardCardButton.id ]: {
			id: standardCardButton.id,
			enabled: true,
		},
	} );
	await wooCommerceApi.deleteAllOrders();
} );

for ( const testOrder of standardCardButtonClassicCheckout ) {
	transactionsOnClassicCheckout( testOrder );
}

// Excluding Tax
test.describe( () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	for ( const testOrder of standardCardButtonClassicCheckoutExcludingTax ) {
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

	for ( const testOrder of standardCardButtonClassicCheckoutIntentAuthorized ) {
		transactionsOnClassicCheckout( testOrder );
	}

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { authorizeOnly: false } );
	} );
} );
