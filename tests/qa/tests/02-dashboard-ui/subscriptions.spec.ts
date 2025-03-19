/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	storeConfigDefault,
	percyPcpSettingsConfig,
	subscriptionsPlugin,
} from '../../resources';

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

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( subscriptionsPlugin.slug );
	} );
} );