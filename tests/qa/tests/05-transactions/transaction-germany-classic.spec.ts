/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	taxSettings,
	customers,
	negative12FeePlugin,
} from '../../resources';
import {
	transactionsOnClassicCheckout,
} from './_test-scenarios';
import {
	puiClassicCheckout,
	puiClassicCheckoutExcludingTax,
	puiClassicCheckoutNegativeFee,
} from './_test-data/pui';

/**
 * BCDC is classic-checkout only — block checkout is not supported.
 */

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( {
		enableClassicPages: true,
		customer: customers.germany,
	} );
} );

for ( const testOrder of puiClassicCheckout ) {
	transactionsOnClassicCheckout( testOrder );
}

// Excluding Tax
test.describe( () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	for ( const testOrder of puiClassicCheckoutExcludingTax ) {
		transactionsOnClassicCheckout( testOrder );
	}

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );

/**
 * Negative fee snippet
 */

test.describe( () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( negative12FeePlugin.slug );
	} );

	for ( const testOrder of puiClassicCheckoutNegativeFee ) {
		transactionsOnClassicCheckout( testOrder );
	}

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( negative12FeePlugin.slug );
	} );
} );
