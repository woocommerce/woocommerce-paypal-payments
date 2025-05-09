/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import { storeConfigDefault } from '../../resources';
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

		await pcpOnboarding.snapshotContent(
			`${ testInfo.title } - ${ country }`,
			3000
		);
	} );
}

test( 'PCP-4312 | Settings - Onboarding initial page - See advanced options - Default UI', async ( {
	pcpOnboarding,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.snapshotContent( testInfo.title, 3000 );
} );

test( 'PCP-4313 | Settings - Onboarding - Enable Sandbox mode - Default UI', async ( {
	pcpOnboarding,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( true );
	await pcpOnboarding.snapshotContent( testInfo.title, 3000 );
} );

test( 'PCP-4314 | Settings - Onboarding - See advanced options - Manually Connect by clicking on label - Default UI', async ( {
	pcpOnboarding,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( true );
	await pcpOnboarding.toggleManuallyConnect( true );
	await pcpOnboarding.snapshotContent( testInfo.title, 3000 );
} );

test( 'PCP-4315 | Settings - Onboarding - See advanced options - Sandbox mode NOT enabled - Default UI', async ( {
	pcpOnboarding,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( false );
	await pcpOnboarding.toggleManuallyConnect( true );
	await pcpOnboarding.snapshotContent( testInfo.title, 3000 );
} );

test( 'PCP-4316 | Settings - Onboarding - See advanced options - Enable/disable Sandbox mode in Manually connect section - Default UI', async ( {
	pcpOnboarding,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( false );
	await pcpOnboarding.toggleManuallyConnect( false );
	await pcpOnboarding.enableManuallyConnectToggle().click();
	await pcpOnboarding.snapshotContent( testInfo.title, 3000 );
} );

test( 'PCP-4318 | Settings - US - Onboarding - Connect with business account, all product types, card payments enabled', async ( {
	pcpOnboarding,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.activatePayPalPaymentsButton().click();
	await pcpOnboarding.snapshotContent( testInfo.title, 3000 );

	await pcpOnboarding.businessRadio().click();
	await pcpOnboarding.snapshotContent(
		`${ testInfo.title } - Set up store type`
	);
	await pcpOnboarding.continueButton().click();
	await pcpOnboarding.snapshotContent(
		`${ testInfo.title } - Select product types - No option selected`,
		3000
	);

	await pcpOnboarding.physicalGoodsCheckbox().check();
	await pcpOnboarding.virtualCheckbox().check();
	await pcpOnboarding.snapshotContent(
		`${ testInfo.title } - Select product types - Products selected`,
		3000
	);
	await pcpOnboarding.continueButton().click();
	await pcpOnboarding.snapshotContent(
		`${ testInfo.title } - Choose checkout options`
	);
	await pcpOnboarding.disableOptionalPaymentMethodsRadio().click();
	await pcpOnboarding.snapshotContent(
		`${ testInfo.title } - Choose checkout options - Card payments disabled`,
		3000
	);
} );

test.describe( '', () => {
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
			await pcpOnboarding.snapshotLocator( pcpOnboarding.onboardingContentContainer(),
				`${ testKey } - Initial Page`, {timeout: 3000} 
			);

			await pcpOnboarding.activatePayPalPaymentsButton().click();
			if ( country !== 'Germany' ) {
				await pcpOnboarding.businessRadio().click();
				await pcpOnboarding.continueButton().click();
			}
			await pcpOnboarding.physicalGoodsCheckbox().check();
			await pcpOnboarding.continueButton().click();
			await pcpOnboarding.snapshotLocator(pcpOnboarding.onboardingContentContainer(),
				`${ testKey } - Checkout Page` , {timeout: 3000}
			);
		} );
	}
} );
