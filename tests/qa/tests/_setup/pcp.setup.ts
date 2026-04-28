/**
 * Internal dependencies
 */
import {
	test as setup,
	resetEnvironment,
	createStorageStates,
	setupWooCommerce,
} from '../../utils';
import {
	merchants,
	storeConfigGermany,
	storeConfigMexico,
	storeConfigUsa,
	taxSettings,
	products,
	gateways,
} from '../../resources';

const { acdc } = gateways;

setup.describe( 'env:reset;', async () => {
	setup( 'Setup: Reset Environment', async () => {
		await resetEnvironment();
	} );

	setup( 'Setup: Create storage state', async () => {
		await createStorageStates();
	} );

	await setupWooCommerce();
} );

// --- PCP USA ---

setup( 'setup:pcp:usa;', async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigUsa );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
} );

// --- PCP Germany ---

setup( 'setup:pcp:germany;', async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigGermany );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.germany.client_id,
		merchants.germany.client_secret
	);
} );

// --- PCP Mexico ---

setup( 'setup:pcp:mexico;', async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigMexico );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.mexico.client_id,
		merchants.mexico.client_secret
	);
} );

// --- Plugin update ---

setup( 'setup:pcp:update;', async ( { plugins } ) => {
	await plugins.installPluginFromFile(
		'./resources/files/woocommerce-paypal-payments-update.zip'
	);
} );

// --- Checkout layout ---

setup( 'setup:checkout:block;', async ( { utils } ) => {
	await utils.configureStore( { enableClassicPages: false } );
} );

setup( 'setup:checkout:classic;', async ( { utils } ) => {
	await utils.configureStore( { enableClassicPages: true } );
} );

// --- Tax ---

setup( 'setup:tax:inc;', async ( { utils } ) => {
	await utils.configureStore( { taxes: taxSettings.including } );
} );

setup( 'setup:tax:exc;', async ( { utils } ) => {
	await utils.configureStore( { taxes: taxSettings.excluding } );
} );