/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { customers, gateways, negative12FeePlugin, taxSettings } from '../../resources';
import { transactionsOnClassicCheckout } from './_test-scenarios';
import {
	payPalClassicCheckout,
	payPalClassicCheckoutExcludingTax,
	payPalClassicCheckoutIntentAuthorized,
	payPalClassicCheckoutNegativeFee,
} from './_test-data/paypal';
import {
	payLaterClassicCheckout,
	payLaterClassicCheckoutExcludingTax,
	payLaterClassicCheckoutIntentAuthorized,
	payLaterClassicCheckoutNegativeFee,
} from './_test-data/pay-later';
import {
	acdcClassicCheckout,
	acdcClassicCheckoutIntentAuthorized,
	acdcClassicCheckoutExcludingTax,
	acdcClassicCheckout3ds,
	acdcClassicCheckoutNegativeFee,
} from './_test-data/acdc';
import { fastlaneClassicCheckout } from './_test-data/fastlane';

const { fastlane } = gateways;

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( {
		enableClassicPages: true,
		customer: customers.usa,
	} );
} );

for ( const testOrder of payPalClassicCheckout ) {
	transactionsOnClassicCheckout( testOrder );
}

for ( const testOrder of payLaterClassicCheckout ) {
	transactionsOnClassicCheckout( testOrder );
}

for ( const testOrder of acdcClassicCheckout ) {
	transactionsOnClassicCheckout( testOrder );
}

/**
 * Venmo is eligible only for USA/USD
 */
// NOT TESTABLE AT THE MOMENT
// for( const testOrder of venmoClassicCheckoutUsa ) {
// 	transactionsOnClassicCheckout( testOrder );
// }

// for( const testOrder of venmoClassicCartUsa ) {
// 	transactionsOnClassicCart( testOrder );
// }

// for( const testOrder of venmoClassicProductUsa ) {
// 	transactionsOnClassicProduct( testOrder );
// }

// Excluding Tax
test.describe( () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	for ( const testOrder of payPalClassicCheckoutExcludingTax ) {
		transactionsOnClassicCheckout( testOrder );
	}

	for ( const testOrder of payLaterClassicCheckoutExcludingTax ) {
		transactionsOnClassicCheckout( testOrder );
	}

	for ( const testOrder of acdcClassicCheckoutExcludingTax ) {
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

	for ( const testOrder of payPalClassicCheckoutIntentAuthorized ) {
		transactionsOnClassicCheckout( testOrder );
	}

	for ( const testOrder of payLaterClassicCheckoutIntentAuthorized ) {
		transactionsOnClassicCheckout( testOrder );
	}

	for ( const testOrder of acdcClassicCheckoutIntentAuthorized ) {
		transactionsOnClassicCheckout( testOrder );
	}

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { authorizeOnly: false } );
	} );
} );

// ACDC 3DS
test.describe( () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( {
			threeDSecure: 'always-3d-secure',
		} );
	} );

	for ( const testOrder of acdcClassicCheckout3ds ) {
		transactionsOnClassicCheckout( testOrder );
	}

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( {
			threeDSecure: 'no-3d-secure',
		} );
	} );
} );

/**
 * Fastlane (only for USA)
 */

test.describe( () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( {
			[ fastlane.id ]: { id: fastlane.id, enabled: true },
		} );
	} );

	for ( const testOrder of fastlaneClassicCheckout ) {
		transactionsOnClassicCheckout( testOrder );
	}

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( {
			[ fastlane.id ]: { id: fastlane.id, enabled: false },
		} );
	} );
} );

/**
 * Negative fee snippet
 */

test.describe( () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( negative12FeePlugin.slug );
	} );

	for ( const testOrder of payPalClassicCheckoutNegativeFee ) {
		transactionsOnClassicCheckout( testOrder );
	}

	for ( const testOrder of payLaterClassicCheckoutNegativeFee ) {
		transactionsOnClassicCheckout( testOrder );
	}

	for ( const testOrder of acdcClassicCheckoutNegativeFee ) {
		transactionsOnClassicCheckout( testOrder );
	}

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( negative12FeePlugin.slug );
	} );
} );
