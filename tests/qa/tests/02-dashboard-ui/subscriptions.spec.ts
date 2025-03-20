/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	storeConfigDefault,
	percyPcpSettingsConfig,
	subscriptionsPlugin,
	merchants,
	Pcp
} from '../../resources';
import { customers, orders, products } from 'playwright-utils/src';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await utils.resetPcpDb();
} );

test.describe( () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( subscriptionsPlugin.slug );
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

	test('PCP-4357 | Subscription - Settings - US - Onboarding - Connect with business account, all product types, card payment enabled', async ({ pcpOnboarding, percy, utils, pcpPaymentMethods, pcpSettings, pcpStyling  }, testInfo) => {
		
		await utils.fillVisitorsCart([products.simple10])
		
		await pcpOnboarding.visit();

		await pcpOnboarding.activatePayPalPaymentsButton().click();

		await pcpOnboarding.businessRadio().click();
		await pcpOnboarding.continueButton().click();

		await pcpOnboarding.physicalGoodsCheckbox().check();
		await pcpOnboarding.subscriptionsCheckbox().check();
		await pcpOnboarding.continueButton().click();

		await pcpOnboarding.enableOptionalPaymentMethodsRadio().click();
		await pcpOnboarding.continueButton().click();

		await pcpOnboarding.gotoInitialOnboardingPage();

		await utils.connectMerchant(merchants.usa);
		await pcpPaymentMethods.visit();
		await percy.takeSnapshot(`${ testInfo.title } - Payment methods - PayPal, Venmo, ACDC enabled - All APMs enabled - Default`, percyPcpSettingsConfig);

		await pcpSettings.visit();
		await percy.takeSnapshot(`${ testInfo.title } - Settings - Pay Now Experience enabled - Default`, percyPcpSettingsConfig);

		await pcpStyling.visit();
		await percy.takeSnapshot(`${ testInfo.title } - Styling - Default (For Cart- Paypal and Venmo enabled)`, percyPcpSettingsConfig);

		const snapshotName = testInfo.title;
		const locations: Pcp.Admin.Styling.Location[] = [
				'Classic Checkout',
				'Express Checkout',
				'Mini Cart',
				'Product Page',
			];
			for ( const location of locations ) {
				await pcpStyling.locationSelectbox().selectOption( location );
				await pcpStyling.snapshotStylingConfigurator(
					`${ snapshotName } - ${ location }`
				);
			}


	

	
} );
test.afterAll( async ( { requestUtils } ) => {
	await requestUtils.deactivatePlugin( subscriptionsPlugin.slug );
} );
});
