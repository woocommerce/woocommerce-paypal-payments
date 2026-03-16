/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	merchants,
	storeConfigUsa,
	gateways,
	taxSettings,
	customers,
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
import {
	payPalClassicCheckout,
	payPalClassicCheckoutExcludingTax,
	payPalClassicCheckoutIntentAuthorized,
} from './_test-data/paypal';
import {
	payLaterClassicCheckout,
	payLaterClassicCheckoutExcludingTax,
	payLaterClassicCheckoutIntentAuthorized,
} from './_test-data/pay-later';
import {
	acdcClassicCheckout,
	acdcClassicCheckoutIntentAuthorized,
	acdcClassicCheckoutExcludingTax,
	acdcClassicCheckout3ds,
} from './_test-data/acdc';
import { fastlaneClassicCheckout } from './_test-data/fastlane';

const { payPal, payLater, venmo, acdc, fastlane } = gateways;

test.beforeAll( async ( { utils, pcpApi, wooCommerceApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		enableClassicPages: true,
		customer: customers.usa,
	} );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
	await pcpApi.updatePcpPaymentMethods( {
		[ payPal.id ]: { id: payPal.id, enabled: true },
		[ payLater.id ]: { id: payLater.id, enabled: true },
		[ venmo.id ]: { id: venmo.id, enabled: true },
		[ acdc.id ]: { id: acdc.id, enabled: true },
		[ fastlane.id ]: { id: fastlane.id, enabled: false },
	} );
	await wooCommerceApi.deleteAllOrders();
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
