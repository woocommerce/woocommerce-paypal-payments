/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import { storeConfigDefault, subscriptionsPlugin } from '../../resources';

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
} );

test.describe( () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( subscriptionsPlugin.slug );
	} );

	test( 'PCP-4356 | Subscription - Settings - US - Onboarding - Connect with personal account - Subscription type of product not allowed', async ( {
		pcpOnboarding,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.activatePayPalPaymentsButton().click();

		await expect( pcpOnboarding.selectBoxContentDetail() ).toHaveText(
			'* Business account is required for subscriptions.'
		);
		await pcpOnboarding.personalAccountRadio().click();
		await pcpOnboarding.snapshotContent(
			`${ testInfo.title } - Set up store type`
		);
		await pcpOnboarding.continueButton().click();

		await expect( pcpOnboarding.subscriptionsCheckbox() ).toBeVisible();
		await expect( pcpOnboarding.subscriptionsCheckbox() ).toHaveAttribute(
			'disabled'
		);
		await pcpOnboarding.snapshotContent(
			`${ testInfo.title } - Select product types - Subscription type of product not allowed`
		);
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( subscriptionsPlugin.slug );
	} );
} );
