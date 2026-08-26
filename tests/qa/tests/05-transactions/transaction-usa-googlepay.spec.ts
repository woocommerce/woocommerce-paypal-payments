/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	transactionsOnCheckout,
	transactionsOnPayByLink,
	transactionsOnProduct,
} from './_test-scenarios';
import {
	googlePayCheckout,
	googlePayProduct,
	googlePayPayByLink,
	googlePayCheckoutNegativeFee,
} from './_test-data/googlepay';
import { GooglePayPopup } from '../../utils/frontend/google-pay-popup';
import { customers, negative12FeePlugin } from '../../resources';

test.use( {
	// Strip Basic Auth from the visitor context — when present it breaks
	// Playwright's popup-event tracking for cross-origin window.open() calls.
	httpCredentials: undefined,
	screenshot: 'off',
	trace: 'off',
	video: 'off',
	launchOptions: {
		args: [
			'--disable-web-security',
			'--disable-blink-features=AutomationControlled',
			'--disable-features=UserAgentClientHint',
		],
	},
} );

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( {
		enableClassicPages: false,
		customer: customers.usa,
	} );
} );

test.beforeEach( async ( { visitorPage } ) => {
	await GooglePayPopup.applyBrowserPatches( visitorPage.context() );
} );

for ( const testOrder of googlePayCheckout ) {
	transactionsOnCheckout( testOrder );
}

for ( const testOrder of googlePayProduct ) {
	transactionsOnProduct( testOrder );
}

for ( const testOrder of googlePayPayByLink ) {
	transactionsOnPayByLink( testOrder );
}

/**
 * Negative fee snippet
 */

test.describe( () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( negative12FeePlugin.slug );
	} );

	for ( const testOrder of googlePayCheckoutNegativeFee ) {
		transactionsOnCheckout( testOrder );
	}
	
	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( negative12FeePlugin.slug );
	} );
} );
