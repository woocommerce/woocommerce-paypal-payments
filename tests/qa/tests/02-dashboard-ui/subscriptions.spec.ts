/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import { storeConfigDefault, subscriptionsPlugin, merchants, Pcp } from '../../resources';

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
} );

test.describe( () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( subscriptionsPlugin.slug );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( subscriptionsPlugin.slug );
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

	test( 'PCP-4357 | Subscription - Settings - US - Onboarding - Connect with business account, all product types, card payment enabled', async ( {
		pcpOnboarding,
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
	
		await pcpOnboarding.page.reload();
		await pcpOnboarding.snapshotContent(
			`${ testInfo.title } - Overview`, 3000
		);
	
	
		await pcpSettings.visit();
		await pcpOnboarding.snapshotContent(
			`${ testInfo.title } - Settings - Pay Now enabled by default`, 3000
		);
	
		await pcpPaymentMethods.visit();
		await pcpOnboarding.snapshotContent(
			`${ testInfo.title } - Payment methods - PayPal, Venmo enabled`, 3000
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
} );
