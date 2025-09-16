/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import { storeConfigDefault, subscriptionsPlugin } from '../../resources';
import { defaultUiTestData, onboardingCheckoutComparison } from './_test-data';

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
} );

for ( const country of defaultUiTestData ) {
	test( `${ country.testSummary }`, async ( {
		pcpOnboarding,
		wooCommerceApi,
	}, testInfo ) => {
		await wooCommerceApi.updateGeneralSettings(
			country.wooCommerceGeneralSettings
		);

		await pcpOnboarding.visit();
		await pcpOnboarding.gotoInitialOnboardingPage();
		await pcpOnboarding.page.waitForLoadState();

		const noWarnings = await pcpOnboarding.assertNoBadgeBoxUtilsWarnings();
		expect( noWarnings ).toBeTruthy();

		await pcpOnboarding.snapshotLocator(
			pcpOnboarding.onboardingContentContainer(),
			`${ testInfo.title } - ${ country }`,
			{ timeout: 3000 }
		);
	} );
}

test( 'PCP-4312 | Settings - Onboarding initial page - See advanced options - Default UI', async ( {
	pcpOnboarding,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.snapshotLocator(
		pcpOnboarding.onboardingContentContainer(),
		testInfo.title,
		{ timeout: 3000 }
	);
} );

test( 'PCP-4313 | Settings - Onboarding - Enable Sandbox mode - Default UI', async ( {
	pcpOnboarding,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( true );
	await pcpOnboarding.snapshotLocator(
		pcpOnboarding.onboardingContentContainer(),
		testInfo.title,
		{ timeout: 3000 }
	);
} );

test( 'PCP-4314 | Settings - Onboarding - See advanced options - Manually Connect by clicking on label - Default UI', async ( {
	pcpOnboarding,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( true );
	await pcpOnboarding.toggleManuallyConnect( true );
	await pcpOnboarding.snapshotLocator(
		pcpOnboarding.onboardingContentContainer(),
		testInfo.title,
		{ timeout: 3000 }
	);
} );

test( 'PCP-4315 | Settings - Onboarding - See advanced options - Sandbox mode NOT enabled - Default UI', async ( {
	pcpOnboarding,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( false );
	await pcpOnboarding.toggleManuallyConnect( true );
	await pcpOnboarding.snapshotLocator(
		pcpOnboarding.onboardingContentContainer(),
		testInfo.title,
		{ timeout: 3000 }
	);
} );

test( 'PCP-4316 | Settings - Onboarding - See advanced options - Enable/disable Sandbox mode in Manually connect section - Default UI', async ( {
	pcpOnboarding,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( false );
	await pcpOnboarding.toggleManuallyConnect( false );
	await pcpOnboarding.enableManuallyConnectToggle().click();
	await pcpOnboarding.snapshotLocator(
		pcpOnboarding.onboardingContentContainer(),
		testInfo.title,
		{ timeout: 3000 }
	);
} );

test.describe( () => {
	for ( const testData of onboardingCheckoutComparison ) {
		const { testKey, country, wooCommerceGeneralSettings } = testData;
		test( `${ testKey } | Settings - ${ country } - Onboarding - Compare initial onboarding page (right part) with expanded checkout screen`, async ( {
			pcpOnboarding,
			wooCommerceApi,
		}, testInfo ) => {
			await wooCommerceApi.updateGeneralSettings(
				wooCommerceGeneralSettings
			);

			await pcpOnboarding.visit();
			await pcpOnboarding.gotoInitialOnboardingPage();
			await pcpOnboarding.page.waitForLoadState();
			await pcpOnboarding.snapshotLocator(
				pcpOnboarding.onboardingContentContainer(),
				`${ testKey } - Initial Page`,
				{ timeout: 3000 }
			);

			await pcpOnboarding.activatePayPalPaymentsButton().click();
			if ( country !== 'Germany' ) {
				await pcpOnboarding.businessRadio().click();
				await pcpOnboarding.continueButton().click();
			}
			await pcpOnboarding.physicalGoodsCheckbox().check();
			await pcpOnboarding.continueButton().click();
			await pcpOnboarding.snapshotLocator(
				pcpOnboarding.onboardingContentContainer(),
				`${ testKey } - Checkout Page`,
				{ timeout: 3000 }
			);
		} );
	}
} );

test.describe( () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'woopayments' );
	} );

	test.afterAll( async ( { requestUtils, plugins } ) => {
		await requestUtils.deactivatePlugin( 'woopayments' );
	} );

	test( 'PCP-4382 | WooPayments - Settings - Onboarding - Default UI (bcdc, paylater)', async ( {
		pcpOnboarding,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.snapshotLocator(
			pcpOnboarding.onboardingContentContainer(),
			`${ testInfo.title } - Initial Page`,
			{ timeout: 3000 }
		);

		await pcpOnboarding.activatePayPalPaymentsButton().click();
		await pcpOnboarding.businessRadio().click();
		await pcpOnboarding.continueButton().click();
		await pcpOnboarding.physicalGoodsCheckbox().check();
		await pcpOnboarding.continueButton().click();
		await pcpOnboarding.snapshotLocator(
			pcpOnboarding.onboardingContentContainer(),
			`${ testInfo.title } - Product types`,
			{ timeout: 3000 }
		);
	} );

	test( 'PCP-4400 | WooPayments - Settings - Onboarding - No cards by default - Serbia', async ( {
		wooCommerceApi,
		pcpOnboarding,
	}, testInfo ) => {
		wooCommerceApi.updateGeneralSettings( {
			woocommerce_default_country: 'RS:RS00',
			woocommerce_currency: 'EUR',
		} );

		await pcpOnboarding.visit();
		await pcpOnboarding.gotoInitialOnboardingPage();
		await pcpOnboarding.snapshotLocator(
			pcpOnboarding.onboardingContentContainer(),
			testInfo.title,
			{ timeout: 3000 }
		);
	} );
} );

test( 'PCP-4403 | Settings - Zimbabwe - Onboarding  - Country not eligible for PayPal payments', async ( {
	wooCommerceApi,
	pcpOnboarding,
}, testInfo ) => {
	await wooCommerceApi.updateGeneralSettings( {
		woocommerce_default_country: 'ZW',
		woocommerce_currency: 'USD',
	} );
	await pcpOnboarding.visit();
	await pcpOnboarding.snapshotContent( testInfo.title, 3000 );
} );
