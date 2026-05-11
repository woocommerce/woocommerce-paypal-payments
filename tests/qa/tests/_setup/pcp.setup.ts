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
	customers,
	gateways,
} from '../../resources';

const { payPal, payLater, venmo, acdc, fastlane, googlepay } = gateways;

setup.describe( 'e2e:env:reset;', async () => {
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

setup(
	'setup:pcp:usa:transactions;',
	async ( { utils, pcpApi, wooCommerceApi } ) => {
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
	}
);

setup( 'setup:pcp:usa:refund;', async ( { utils, pcpApi, wooCommerceApi } ) => {
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
		[ acdc.id ]: { id: acdc.id, enabled: true },
	} );
	await wooCommerceApi.deleteAllOrders();
} );

setup(
	'setup:pcp:usa:vaulting;',
	async ( { utils, pcpApi, wooCommerceApi } ) => {
		await utils.configureStore( storeConfigUsa );
		await utils.installAndActivatePcp();
		await pcpApi.resetDb();
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
		} );
		await wooCommerceApi.deleteAllOrders();
	}
);

setup(
	'setup:pcp:usa:subscription;',
	async ( { utils, pcpApi, wooCommerceApi } ) => {
		await utils.configureStore( {
			...storeConfigUsa,
			enableWpDebugging: false,
			enableSubscriptionsPlugin: true,
			products: [
				products.subscription100,
				products.subscriptionFreeTrial,
			],
		} );
		await utils.installAndActivatePcp();
		await pcpApi.resetDb();
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
		await wooCommerceApi.deleteAllOrders();
	}
);

setup(
	'setup:pcp:usa:transactions:googlepay;',
	async ( { utils, pcpApi, wooCommerceApi } ) => {
		await utils.configureStore( {
			...storeConfigUsa,
			enableClassicPages: false,
			customer: customers.usa,
		} );
		await utils.installAndActivatePcp();
		await pcpApi.resetDb();
		await pcpApi.connectMerchant(
			merchants.usa.client_id,
			merchants.usa.client_secret
		);
		await pcpApi.updatePcpPaymentMethods( {
			[ acdc.id ]: { id: acdc.id, enabled: true },
			[ googlepay.id ]: { id: googlepay.id, enabled: true },
		} );
		await wooCommerceApi.deleteAllOrders();
	}
);

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
