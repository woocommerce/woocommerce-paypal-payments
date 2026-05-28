/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
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
 * BCDC is classic-checkout only — block checkout is not supported.
 */

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( {
		enableClassicPages: true,
		customer: customers.mexico,
	} );
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
