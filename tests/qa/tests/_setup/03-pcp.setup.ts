/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
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

// =====================================================================
// Layer 2 — PCP country: configureStore + installPcp + resetDb + connect
// =====================================================================

setup( 'setup:pcp:usa;', async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigUsa );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
} );

setup( 'setup:pcp:germany;', async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigGermany );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.germany.client_id,
		merchants.germany.client_secret
	);
} );

setup( 'setup:pcp:mexico;', async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigMexico );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.mexico.client_id,
		merchants.mexico.client_secret
	);
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

setup( 'setup:vaulting;', async ( { pcpApi } ) => {
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret,
		{
			isCasualSeller: false,
			areOptionalPaymentMethodsEnabled: true,
		}
	);
	await pcpApi.updatePcpSettings( {
		savePaypalAndVenmo: true,
		saveCardDetails: true,
	} );
	await pcpApi.updatePcpPaymentMethods( {
		[ payPal.id ]: { id: payPal.id, enabled: true },
		[ acdc.id ]: { id: acdc.id, enabled: true },
		[ fastlane.id ]: { id: fastlane.id, enabled: false },
	} );
} );

// --- Subscription (re-connects with advanced + subscription products) ---

setup( 'setup:subscription;', async ( { utils, pcpApi } ) => {
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