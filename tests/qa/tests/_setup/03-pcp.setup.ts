/**
 * External dependencies
 */
import { APIRequestContext } from '@playwright/test';
/**
 * Internal dependencies
 */
import { test as setup, expect, PcpApi } from '../../utils';
import {
	merchants,
	storeConfigGermany,
	storeConfigMexico,
	storeConfigUsa,
	products,
	customers,
	gateways,
} from '../../resources';

const { payPal, payLater, venmo, acdc, bcdc, fastlane, googlepay, oxxo, pui } = gateways;

/**
 * In CI, confirms the webhook URL PayPal has on file was rewritten to the
 * public ngrok host (via NGROK_HOST, see IncomingWebhookEndpoint::url())
 * instead of the local site host, and that it's actually reachable. This
 * catches a broken tunnel right after connect, instead of as a confusing
 * multi-minute timeout deep in a transaction test.
 *
 * @param pcpApi  The PCP API client, used to read back the registered webhook URL.
 * @param request The Playwright request context, used to probe the URL.
 */
const assertWebhookPubliclyReachable = async (
	pcpApi: PcpApi,
	request: APIRequestContext
) => {
	if ( ! process.env.CI ) {
		return;
	}

	const { data } = await pcpApi.wcRequest( 'get', 'wc_paypal/webhooks' );
	const webhookUrl = data?.url;

	expect(
		webhookUrl,
		'Assert a webhook URL is registered with PayPal'
	).toBeTruthy();

	const registeredHost = new URL( webhookUrl ).hostname;
	const localHost = new URL( process.env.WP_BASE_URL ).hostname;

	expect(
		registeredHost,
		`Assert the registered webhook host (${ registeredHost }) was rewritten to the public ngrok host, not the local site host (${ localHost })`
	).not.toEqual( localHost );

	let isReachable = true;
	try {
		await request.get( webhookUrl, { timeout: 15_000 } );
	} catch {
		isReachable = false;
	}
	expect(
		isReachable,
		`Assert the registered webhook URL (${ webhookUrl }) is publicly reachable`
	).toBeTruthy();
};

// =====================================================================
// Layer 2 — PCP country: configureStore + installPcp + resetDb + connect
// =====================================================================

setup( 'setup:pcp:usa;', async ( { utils, pcpApi, request } ) => {
	await utils.configureStore( storeConfigUsa );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
	await assertWebhookPubliclyReachable( pcpApi, request );
} );

setup( 'setup:pcp:germany;', async ( { utils, pcpApi, request } ) => {
	await utils.configureStore( storeConfigGermany );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.germany.client_id,
		merchants.germany.client_secret
	);
	await assertWebhookPubliclyReachable( pcpApi, request );
} );

setup( 'setup:pcp:mexico;', async ( { utils, pcpApi, request } ) => {
	await utils.configureStore( storeConfigMexico );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.mexico.client_id,
		merchants.mexico.client_secret
	);
	await assertWebhookPubliclyReachable( pcpApi, request );
} );

// =====================================================================
// Layer 3 — Feature config (assumes Layer 2 already ran for the country)
// =====================================================================

// --- Transactions ---

setup( 'setup:transaction:usa;', async ( { utils, pcpApi } ) => {
	await utils.configureStore( { customer: customers.usa } );
	await pcpApi.updatePcpPaymentMethods( {
		[ payPal.id ]: { id: payPal.id, enabled: true },
		[ payLater.id ]: { id: payLater.id, enabled: true },
		[ venmo.id ]: { id: venmo.id, enabled: true },
		[ acdc.id ]: { id: acdc.id, enabled: true },
		[ googlepay.id ]: { id: googlepay.id, enabled: true },
		[ fastlane.id ]: { id: fastlane.id, enabled: false },
	} );
	await pcpApi.updatePcpStyling( {
		cart: {
			enabled: true,
			methods: [ payPal.id, payLater.id, venmo.id, googlepay.id ],
		},
		product: {
			enabled: true,
			methods: [ payPal.id, payLater.id, venmo.id, googlepay.id ],
		},
	} );
} );

setup( 'setup:transaction:mexico;', async ( { utils, pcpApi } ) => {
	await utils.configureStore( {
		enableClassicPages: true,
		customer: customers.mexico,
	} );
	await pcpApi.updatePcpPaymentMethods( {
		[ payPal.id ]: { id: payPal.id, enabled: true },
		[ bcdc.id ]: { id: bcdc.id, enabled: true },
		[ oxxo.id ]: { id: oxxo.id, enabled: true },
	} );
} );

setup( 'setup:transaction:germany;', async ( { utils, pcpApi } ) => {
	await utils.configureStore( {
		enableClassicPages: true,
		customer: customers.germany,
	} );
	await pcpApi.updatePcpPaymentMethods( {
		[ payPal.id ]: { id: payPal.id, enabled: true },
		[ pui.id ]: { id: pui.id, enabled: true },
		puiBrandName: 'PUI Test',
		puiCustomerServiceInstructions: 'Test PUI',
		puiLogoUrl: 'www.logo-test.com',
	} );
} );

// --- Vaulting (re-connects with advanced merchant options) ---

setup( 'setup:vaulting;', async ( { pcpApi, request } ) => {
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret,
		{
			isCasualSeller: false,
			areOptionalPaymentMethodsEnabled: true,
		}
	);
	await assertWebhookPubliclyReachable( pcpApi, request );
	await pcpApi.updatePcpSettings( {
		savePaypalAndVenmo: true,
		saveCardDetails: true,
	} );
	await pcpApi.updatePcpPaymentMethods( {
		[ payPal.id ]: { id: payPal.id, enabled: true },
		[ acdc.id ]: { id: acdc.id, enabled: true },
	} );
} );

// --- Subscription (re-connects with advanced + subscription products) ---

setup( 'setup:subscription;', async ( { utils, pcpApi, request } ) => {
	await utils.configureStore( {
		enableWpDebugging: false,
		enableSubscriptionsPlugin: true,
		products: [ products.subscription100, products.subscriptionFreeTrial ],
	} );
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret,
		{
			isCasualSeller: false,
			areOptionalPaymentMethodsEnabled: true,
			products: [ 'physical', 'virtual', 'subscriptions' ],
		}
	);
	await assertWebhookPubliclyReachable( pcpApi, request );
	await pcpApi.updatePcpSettings( {
		savePaypalAndVenmo: true,
		saveCardDetails: true,
	} );
	await pcpApi.updatePcpPaymentMethods( {
		[ payPal.id ]: { id: payPal.id, enabled: true },
		[ acdc.id ]: { id: acdc.id, enabled: true },
	} );
} );

// --- Plugin update ---

setup( 'setup:pcp:update;', async ( { plugins } ) => {
	await plugins.installPluginFromFile(
		'./resources/files/woocommerce-paypal-payments-update.zip'
	);
} );