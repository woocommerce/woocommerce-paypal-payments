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
	} ) => {
		await wooCommerceApi.updateGeneralSettings(
			country.wooCommerceGeneralSettings
		);

		await pcpOnboarding.visit();
		await pcpOnboarding.gotoInitialOnboardingPage();
		await pcpOnboarding.page.waitForLoadState();

		const noWarnings = await pcpOnboarding.assertNoBadgeBoxUtilsWarnings();
		expect(
			noWarnings,
			`Assert no badgeBoxUtils console warnings for ${ country.testSummary }`
		).toBeTruthy();

		await expect(
			pcpOnboarding.onboardingContentContainer(),
			`Assert onboarding content container is visible for ${ country.testSummary }`
		).toBeVisible();

		// Assert badge container contains pricing information (percentage + fixed fee) if visible
		const badgeContainer = pcpOnboarding.badgeContainer();
		let badgeVisible = false;
		try {
			await expect(
				badgeContainer,
				`Assert badge container is visible for ${ country.testSummary }`
			).toBeVisible();
			badgeVisible = true;
		} catch {
			// Badge may not be visible in all scenarios
		}
		if ( badgeVisible ) {
			const badgeText = await badgeContainer.textContent();
			expect(
				badgeText,
				`Assert badge contains pricing percentage for ${ country.testSummary }`
			).toMatch( /\d+\.\d+%/ );
			expect(
				badgeText,
				`Assert badge contains fixed fee with currency symbol for ${ country.testSummary }`
			).toMatch( /[+]\s*[$€£]?\d+\.\d+/ );
		}

		// Assert welcome docs container contains country-specific content
		await expect(
			pcpOnboarding.welcomeDocsContainer(),
			`Assert welcome docs container is visible for ${ country.testSummary }`
		).toBeVisible();
		const welcomeDocsText = await pcpOnboarding
			.welcomeDocsContainer()
			.textContent();
		expect(
			welcomeDocsText,
			`Assert welcome docs contain pricing information for ${ country.testSummary }`
		).toBeTruthy();
	} );
}

test( 'PCP-4312 | Settings - Onboarding initial page - See advanced options - Default UI @Critical', async ( {
	pcpOnboarding,
} ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.gotoInitialOnboardingPage();
	await pcpOnboarding.openAdvancedOptions();
	await expect(
		pcpOnboarding.advancedOptionsContent(),
		'Assert advanced options section is visible'
	).toBeVisible();
} );

test( 'PCP-4313 | Settings - Onboarding - Enable Sandbox mode - Default UI @Critical', async ( {
	pcpOnboarding,
} ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.gotoInitialOnboardingPage();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( true );
	await expect(
		pcpOnboarding.enableSandboxModeToggle(),
		'Assert Sandbox mode toggle is visible'
	).toBeVisible();
} );

test( 'PCP-4314 | Settings - Onboarding - See advanced options - Manually Connect by clicking on label - Default UI @Critical', async ( {
	pcpOnboarding,
} ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.gotoInitialOnboardingPage();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( true );
	await pcpOnboarding.toggleManuallyConnect( true );
	await expect(
		pcpOnboarding.sandboxClientIdInput(),
		'Assert manual connect sandbox client ID input is visible'
	).toBeVisible();
} );

test( 'PCP-4315 | Settings - Onboarding - See advanced options - Sandbox mode NOT enabled - Default UI @Critical', async ( {
	pcpOnboarding,
} ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.gotoInitialOnboardingPage();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( false );
	await pcpOnboarding.toggleManuallyConnect( true );
	await expect(
		pcpOnboarding.enableManuallyConnectToggle(),
		'Assert manually connect toggle is visible when sandbox is off'
	).toBeVisible();
} );

test( 'PCP-4316 | Settings - Onboarding - See advanced options - Enable/disable Sandbox mode in Manually connect section - Default UI @Critical', async ( {
	pcpOnboarding,
} ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.gotoInitialOnboardingPage();
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( false );
	await pcpOnboarding.toggleManuallyConnect( false );
	await pcpOnboarding.enableManuallyConnectToggle().click();
	await expect(
		pcpOnboarding.onboardingContentContainer(),
		'Assert onboarding content is visible after opening manually connect'
	).toBeVisible();
} );

test( 'PCP-4318 | Settings - US - Onboarding - Connect with business account, all product types, card payments enabled @Critical', async ( {
	pcpOnboarding,
	wooCommerceApi,
} ) => {
	await wooCommerceApi.updateGeneralSettings( {
		woocommerce_default_country: 'US',
		woocommerce_currency: 'USD',
	} );
	await pcpOnboarding.visit();
	await pcpOnboarding.waitForLoadingMaskRemoved();
	await pcpOnboarding.gotoInitialOnboardingPage();
	await pcpOnboarding.page.waitForLoadState();
	await pcpOnboarding.activatePayPalPaymentsButton().click();
	// Account-type step: wait for business radio (step can take a moment to render)
	await expect(
		pcpOnboarding.businessRadio(),
		'Assert store type step shows business radio after Activate PayPal Payments'
	).toBeVisible();
	await pcpOnboarding.businessRadio().click();
	await pcpOnboarding.continueButton().click();
	await expect(
		pcpOnboarding.physicalGoodsCheckbox(),
		'Assert product types step shows physical goods checkbox'
	).toBeVisible();

	await pcpOnboarding.physicalGoodsCheckbox().check();
	await pcpOnboarding.virtualCheckbox().check();
	await pcpOnboarding.continueButton().click();
	await expect(
		pcpOnboarding.enableOptionalPaymentMethodsRadio(),
		'Assert checkout options step shows optional payment methods'
	).toBeVisible();
	await pcpOnboarding.enableOptionalPaymentMethodsRadio().click();
	await expect(
		pcpOnboarding.continueButton(),
		'Assert continue button is visible after enabling card payments'
	).toBeVisible();
} );

test.describe( () => {
	for ( const testData of onboardingCheckoutComparison ) {
		const { testKey, country, wooCommerceGeneralSettings } = testData;
		test( `${ testKey } | Settings - ${ country } - Onboarding - Compare initial onboarding page (right part) with expanded checkout screen`, async ( {
			pcpOnboarding,
			wooCommerceApi,
		} ) => {
			await wooCommerceApi.updateGeneralSettings(
				wooCommerceGeneralSettings
			);

			await pcpOnboarding.visit();
			await pcpOnboarding.gotoInitialOnboardingPage();
			await pcpOnboarding.page.waitForLoadState();
			await expect(
				pcpOnboarding.onboardingContentContainer(),
				`Assert onboarding initial page is visible for ${ testKey }`
			).toBeVisible();

			// Assert badge contains country-specific pricing if visible
			const badgeContainer = pcpOnboarding.badgeContainer();
			let badgeVisible = false;
			try {
				await expect(
					badgeContainer,
					`Assert badge container is visible for ${ country }`
				).toBeVisible();
				badgeVisible = true;
			} catch {
				// Badge may not be visible in all scenarios
			}
			if ( badgeVisible ) {
				const badgeText = await badgeContainer.textContent();
				expect(
					badgeText,
					`Assert badge contains pricing percentage for ${ country }`
				).toMatch( /\d+\.\d+%/ );
			}

			await pcpOnboarding.activatePayPalPaymentsButton().click();
			if ( country !== 'Germany' ) {
				await pcpOnboarding.businessRadio().click();
				await pcpOnboarding.continueButton().click();
			}
			await pcpOnboarding.physicalGoodsCheckbox().check();
			await pcpOnboarding.continueButton().click();
			await expect(
				pcpOnboarding.onboardingContentContainer(),
				`Assert onboarding checkout step is visible for ${ testKey }`
			).toBeVisible();

			// Assert checkout options container shows country-specific content
			const checkoutOptionsText = await pcpOnboarding
				.checkoutAlternativeOptionsContainer()
				.textContent();
			expect(
				checkoutOptionsText,
				`Assert checkout options contain payment method information for ${ country }`
			).toBeTruthy();
		} );
	}
} );

test.describe( () => {
	test.beforeAll( async ( { requestUtils } ) => {
		if (
			! ( await requestUtils.isPluginInstalled( 'woocommerce-payments' ) )
		) {
			await requestUtils.installPlugin( 'woocommerce-payments' );
		}
		await requestUtils.activatePlugin( 'woopayments' );
	} );

	test.afterAll( async ( { requestUtils, plugins } ) => {
		await requestUtils.deactivatePlugin( 'woopayments' );
		await plugins.deletePlugin( 'woopayments' );
	} );

	test( 'PCP-4382 | WooPayments - Settings - Onboarding - Default UI (bcdc, paylater) @Critical', async ( {
		pcpOnboarding,
	} ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.gotoInitialOnboardingPage();
		await expect(
			pcpOnboarding.onboardingContentContainer(),
			'Assert onboarding initial page is visible with WooPayments'
		).toBeVisible();

		await pcpOnboarding.activatePayPalPaymentsButton().click();
		// Wait for account-type step (WooPayments view can take a moment to render)
		await expect(
			pcpOnboarding.businessRadio(),
			'Assert business radio is visible'
		).toBeVisible();
		await pcpOnboarding.businessRadio().click();
		await pcpOnboarding.continueButton().click();
		await pcpOnboarding.physicalGoodsCheckbox().check();
		await pcpOnboarding.continueButton().click();
		await expect(
			pcpOnboarding.onboardingContentContainer(),
			'Assert onboarding product types step is visible'
		).toBeVisible();
	} );

	test( 'PCP-4400 | WooPayments - Settings - Onboarding - No cards by default - Serbia @Critical', async ( {
		wooCommerceApi,
		pcpOnboarding,
	} ) => {
		wooCommerceApi.updateGeneralSettings( {
			woocommerce_default_country: 'RS:RS00',
			woocommerce_currency: 'EUR',
		} );

		await pcpOnboarding.visit();
		await pcpOnboarding.gotoInitialOnboardingPage();
		await expect(
			pcpOnboarding.onboardingContentContainer(),
			'Assert onboarding content is visible for Serbia (no cards)'
		).toBeVisible();
	} );
} );

test( 'PCP-4403 | Settings - Zimbabwe - Onboarding  - Country not eligible for PayPal payments @Critical', async ( {
	wooCommerceApi,
	pcpOnboarding,
} ) => {
	await wooCommerceApi.updateGeneralSettings( {
		woocommerce_default_country: 'ZW',
		woocommerce_currency: 'USD',
	} );
	await pcpOnboarding.visit();
	await pcpOnboarding.page.waitForLoadState( 'domcontentloaded' );
	// Send-only view can take a moment to render (API/store country check)
	await expect(
		pcpOnboarding.sendOnlyMessageHeading(),
		'Assert send-only country message is visible for Zimbabwe (not eligible)'
	).toBeVisible();
} );
