/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { merchants, storeConfigUsa, gateways, customers } from '../../resources';
import { transactionsOnCheckout } from './_test-scenarios';
import { googlePayCheckout } from './_test-data/googlepay';
import { GooglePayPopup } from '../../utils/frontend/google-pay-popup';

const { acdc, googlepay } = gateways;

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

test.beforeAll( async ( { utils, pcpApi, wooCommerceApi } ) => {
	await utils.configureStore( { ...storeConfigUsa, customer: customers.usa } );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant( merchants.usa.client_id, merchants.usa.client_secret );
	// Google Pay requires ACDC to be enabled alongside it.
	await pcpApi.updatePcpPaymentMethods( {
		[ acdc.id ]: { id: acdc.id, enabled: true },
		[ googlepay.id ]: { id: googlepay.id, enabled: true },
	} );
	await wooCommerceApi.deleteAllOrders();
} );

for ( const order of googlePayCheckout ) {
	transactionsOnCheckout( order );
}
