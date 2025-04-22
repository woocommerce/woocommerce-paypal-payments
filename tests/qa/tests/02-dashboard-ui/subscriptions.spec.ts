/**
 * Internal dependencies
 */
import { test, expect, PcpApi } from '../../utils';
import {
	storeConfigDefault,
	percyPcpSettingsConfig,
	subscriptionsPlugin,
	merchants,
	Pcp,
} from '../../resources';
/**
 * External dependencies
 */

test.beforeAll( async ( { utils, pcpApi, requestUtils } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await requestUtils.activatePlugin( subscriptionsPlugin.slug );
} );

test.afterAll( async ( { requestUtils, pcpApi } ) => {
	await requestUtils.deactivatePlugin( subscriptionsPlugin.slug );
	await pcpApi.resetDb();
} );

test( 'PCP-4356 | Subscription - Settings - US - Onboarding - Connect with personal account - Subscription type of product not allowed', async ( {
	pcpOnboarding,
	percy,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.activatePayPalPaymentsButton().click();

	await expect( pcpOnboarding.selectBoxContentDetail() ).toHaveText(
		'* Business account is required for subscriptions.'
	);
	await pcpOnboarding.personalAccountRadio().click();
	await percy.takeSnapshot(
		`${ testInfo.title } - Set up store type`,
		percyPcpSettingsConfig
	);
	await pcpOnboarding.continueButton().click();

	await expect( pcpOnboarding.subscriptionsCheckbox() ).toBeVisible();
	await expect( pcpOnboarding.subscriptionsCheckbox() ).toHaveAttribute(
		'disabled'
	);
	await percy.takeSnapshot(
		`${ testInfo.title } - Select product types - Subscription type of product not allowed`,
		percyPcpSettingsConfig
	);
} );

test( 'PCP-4357 | Subscription - Settings - US - Onboarding - Connect with business account, all product types, card payment enabled', async ( {
	pcpOnboarding,
	percy,
	utils,
	pcpPaymentMethods,
	pcpSettings,
	pcpStyling,
	pcpApi,
}, testInfo ) => {
	await pcpOnboarding.visit();

	await pcpOnboarding.activatePayPalPaymentsButton().click();

	await pcpOnboarding.businessRadio().click();
	await pcpOnboarding.continueButton().click();

	await pcpOnboarding.physicalGoodsCheckbox().check();
	await pcpOnboarding.subscriptionsCheckbox().check();
	await pcpOnboarding.continueButton().click();

	await pcpOnboarding.chooseOptionalPaymentMethods( true );
	await pcpOnboarding.continueButton().click();

	await pcpOnboarding.gotoInitialOnboardingPage();

	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);

	await pcpOnboarding.page.waitForLoadState();
	await pcpSettings.visit();
	await percy.takeSnapshot(
		`${ testInfo.title } - Settings - Pay Now Experience enabled - Default`,
		percyPcpSettingsConfig
	);

	await pcpPaymentMethods.visit();
	await percy.takeSnapshot(
		`${ testInfo.title } - Payment methods - PayPal, Venmo, ACDC enabled - All APMs enabled - Default`,
		percyPcpSettingsConfig
	);

	const snapshotName = testInfo.title;
	const locations: Pcp.Admin.Styling.Location[] = [
		'Cart',
		'Classic Checkout',
		'Express Checkout',
		'Mini Cart',
		'Product Page',
	];

	await pcpStyling.visit();
	await expect( pcpStyling.configContainer() ).toBeVisible();
	await expect( pcpStyling.locationSelectbox() ).toBeVisible();

	for ( const location of locations ) {
		await pcpStyling.locationSelectbox().selectOption( location );
		await pcpStyling.snapshotStylingConfigurator(
			`${ snapshotName } - ${ location }`
		);
	}
} );
