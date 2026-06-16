/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { transactionsOnCheckout, transactionsOnProduct } from './_test-scenarios';
import { googlePayCheckout, googlePayProduct } from './_test-data/googlepay';
import { GooglePayPopup } from '../../utils/frontend/google-pay-popup';

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

test.beforeEach( async ( { visitorPage } ) => {
	await GooglePayPopup.applyBrowserPatches( visitorPage.context() );
} );

for ( const testOrder of googlePayCheckout ) {
	transactionsOnCheckout( testOrder );
}

for ( const testOrder of googlePayProduct ) {
	transactionsOnProduct( testOrder );
}
