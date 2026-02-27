/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	storeConfigDefault,
	subscriptionsPlugin,
	merchants,
	Pcp,
} from '../../resources';
import { onboardingSubscriptionComparisonByOPM } from './_test-data';

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

	test( 'PCP-4356 | Subscription - Settings - US - Onboarding - Connect with personal account - Subscription type of product not allowed @Critical', async ( {
		pcpOnboarding,
		wooCommerceApi,
	} ) => {
		await wooCommerceApi.updateGeneralSettings( {
			woocommerce_default_country: 'US',
			woocommerce_currency: 'USD',
		} );
		await pcpOnboarding.visit();
		await pcpOnboarding.gotoInitialOnboardingPage();
		await pcpOnboarding.activatePayPalPaymentsButton().click();

		await expect(
			pcpOnboarding.selectBoxContentDetail(),
			'Assert subscription requires business account message is shown'
		).toHaveText( '* Business account is required for subscriptions.' );
		await pcpOnboarding.personalAccountRadio().click();
		await expect(
			pcpOnboarding.onboardingContentContainer(),
			'Assert onboarding content is visible after selecting personal account'
		).toBeVisible();
		await pcpOnboarding.continueButton().click();

		await expect(
			pcpOnboarding.subscriptionsCheckbox(),
			'Assert subscriptions checkbox is visible'
		).toBeVisible();
		await expect(
			pcpOnboarding.subscriptionsCheckbox(),
			'Assert subscriptions checkbox is disabled for personal account'
		).toHaveAttribute( 'disabled' );
		await expect(
			pcpOnboarding.onboardingContentContainer(),
			'Assert product types step shows subscription disabled'
		).toBeVisible();
	} );

	test.describe( () => {
		test.beforeEach( async ( { utils, pcpApi } ) => {
			await pcpApi.resetDb();
		} );

		for ( const testData of onboardingSubscriptionComparisonByOPM ) {
			const { testSummary, optionalPaymentsEnabled } = testData;
			test(
				testSummary,
				async (
					{
						pcpOnboarding,
						pcpPaymentMethods,
						pcpSettings,
						pcpStyling,
						pcpApi,
					},
					testInfo
				) => {
					await pcpOnboarding.visit();
					await pcpOnboarding.activatePayPalPaymentsButton().click();

					await pcpOnboarding.businessRadio().click();
					await pcpOnboarding.continueButton().click();

					await pcpOnboarding.physicalGoodsCheckbox().check();
					await pcpOnboarding.subscriptionsCheckbox().check();
					await pcpOnboarding.continueButton().click();

					await pcpOnboarding.chooseOptionalPaymentMethods(
						optionalPaymentsEnabled
					);
					await pcpOnboarding.continueButton().click();

					await pcpOnboarding.gotoInitialOnboardingPage();

					await pcpApi.connectMerchant(
						merchants.usa.client_id,
						merchants.usa.client_secret
					);

					await pcpOnboarding.page.reload();
					await expect(
						pcpOnboarding.contentContainer(),
						`Assert overview content is visible for ${ testData.testSummary }`
					).toBeVisible();
					await pcpSettings.visit();
					await expect(
						pcpOnboarding.contentContainer(),
						'Assert settings page content is visible (Pay Now enabled by default)'
					).toBeVisible();

					await pcpPaymentMethods.visit();
					await expect(
						pcpOnboarding.contentContainer(),
						'Assert payment methods page content is visible (PayPal, Venmo enabled)'
					).toBeVisible();

					const locations: Pcp.Admin.Styling.Location[] = [
						'Cart',
						'Classic Checkout',
						'Express Checkout',
						'Mini Cart',
						'Product Page',
					];

					await pcpStyling.visit();
					await expect(
						pcpStyling.configContainer(),
						'Assert styling config container is visible'
					).toBeVisible();
					await expect(
						pcpStyling.locationSelectbox(),
						'Assert styling location selectbox is visible'
					).toBeVisible();

					for ( const location of locations ) {
						await pcpStyling
							.locationSelectbox()
							.selectOption( location );
						await expect(
							pcpStyling.configContainer(),
							`Assert styling config is visible for location ${ location }`
						).toBeVisible();
					}
				}
			);
		}
	} );
} );
