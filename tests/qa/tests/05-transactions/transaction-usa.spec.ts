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
	transactionsOnCheckout,
	transactionsOnPayByLink,
} from './_test-scenarios';
import {
	payPalCheckout,
	payPalCheckoutExcludingTax,
	payPalCheckoutIntentAuthorized,
	payPalPayByLink,
	payPalPayByLinkExcludingTax,
	payPalPayByLinkIntentAuthorized,
} from './_test-data/paypal';
import {
	payLaterCheckout,
	payLaterCheckoutExcludingTax,
	payLaterCheckoutIntentAuthorized,
} from './_test-data/pay-later';
import {
	acdcCheckout,
	acdcCheckoutExcludingTax,
	acdcCheckoutIntentAuthorized,
	acdcCheckout3ds,
	acdcPayByLink,
	acdcPayByLink3ds,
	acdcPayByLinkExcludingTax,
	acdcPayByLinkIntentAuthorized,
} from './_test-data/acdc';
import { fastlaneCheckout } from './_test-data/fastlane';

const { payPal, payLater, venmo, acdc, fastlane } = gateways;

test.beforeAll( async ( { utils, pcpApi, wooCommerceApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
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

for ( const testOrder of payPalCheckout ) {
	transactionsOnCheckout( testOrder );
}

for ( const testOrder of payLaterCheckout ) {
	transactionsOnCheckout( testOrder );
}

for ( const testOrder of acdcCheckout ) {
	transactionsOnCheckout( testOrder );
}

for ( const testOrder of payPalPayByLink ) {
	transactionsOnPayByLink( testOrder );
}

for ( const testOrder of acdcPayByLink ) {
	transactionsOnPayByLink( testOrder );
}

// Excluding Tax
test.describe( () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	for ( const testOrder of payPalCheckoutExcludingTax ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of payLaterCheckoutExcludingTax ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of acdcCheckoutExcludingTax ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of payPalPayByLinkExcludingTax ) {
		transactionsOnPayByLink( testOrder );
	}

	for ( const testOrder of acdcPayByLinkExcludingTax ) {
		transactionsOnPayByLink( testOrder );
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

	for ( const testOrder of payPalCheckoutIntentAuthorized ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of payLaterCheckoutIntentAuthorized ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of acdcCheckoutIntentAuthorized ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of payPalPayByLinkIntentAuthorized ) {
		transactionsOnPayByLink( testOrder );
	}

	for ( const testOrder of acdcPayByLinkIntentAuthorized ) {
		transactionsOnPayByLink( testOrder );
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

	for ( const testOrder of acdcCheckout3ds ) {
		transactionsOnCheckout( testOrder );
	}

	for ( const testOrder of acdcPayByLink3ds ) {
		transactionsOnPayByLink( testOrder );
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

	for ( const testOrder of fastlaneCheckout ) {
		transactionsOnCheckout( testOrder );
	}

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( {
			[ fastlane.id ]: { id: fastlane.id, enabled: false },
		} );
	} );
} );
