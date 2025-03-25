/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	storeConfigDefault,
	percyPcpSettingsConfig,
	subscriptionsPlugin,
} from '../../resources';
import {
	badgeTestsData,
	defaultUiTestData,
} from './_test-data/ui-tests-per-country.data';

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
} );

test.describe.serial( () => {
	for ( const country of defaultUiTestData ) {
		test( `${ country.testSummary }`, async ( {
			pcpOnboarding,
			percy,
			wooCommerceApi,
		}, testInfo ) => {
			await wooCommerceApi.updateGeneralSettings(
				country.wooCommerceGeneralSettings
			);

			await pcpOnboarding.visit();
			await pcpOnboarding.gotoInitialOnboardingPage();
			await pcpOnboarding.page.waitForLoadState();

			const noWarnings =
				await pcpOnboarding.assertNoBadgeBoxUtilsWarnings();
			expect( noWarnings ).toBeTruthy();

			await percy.takeSnapshot(
				`${ testInfo.title } - ${ country }`,
				percyPcpSettingsConfig
			);
		} );
	}

	test( 'PCP-4312 | Settings - Onboarding initial page - See advanced options - Default UI @percy', async ( {
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.openAdvancedOptions();
		await percy.takeSnapshot( testInfo.title, percyPcpSettingsConfig );
	} );
	test( 'PCP-4313 | Settings - Onboarding - Enable Sandbox mode - Default UI @percy', async ( {
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.openAdvancedOptions();
		await pcpOnboarding.toggleSandboxMode( true );
		await percy.takeSnapshot( testInfo.title, percyPcpSettingsConfig );
	} );

	test( 'PCP-4314 | Settings - Onboarding - See advanced options - Manually Connect by clicking on label - Default UI @percy', async ( {
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.openAdvancedOptions();
		await pcpOnboarding.toggleManuallyConnect( true );
		await percy.takeSnapshot( testInfo.title, percyPcpSettingsConfig );
	} );

	test( 'PCP-4315 | Settings - Onboarding - See advanced options - Sandbox mode NOT enabled - Default UI @percy', async ( {
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.openAdvancedOptions();
		await pcpOnboarding.toggleSandboxMode( false );
		await pcpOnboarding.toggleManuallyConnect( true );
		await percy.takeSnapshot( testInfo.title, percyPcpSettingsConfig );
	} );

	test( 'PCP-4316 | Settings - Onboarding - See advanced options - Enable/disable Sandbox mode in Manually connect section - Default UI @percy', async ( {
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.openAdvancedOptions();
		await pcpOnboarding.toggleSandboxMode( false );
		await pcpOnboarding.toggleManuallyConnect( false );
		await pcpOnboarding.enableManuallyConnectToggle().click();
		await percy.takeSnapshot( testInfo.title, percyPcpSettingsConfig );
	} );
} );

test.describe( () => {
	const currencies = [ 'USD', 'GBP', 'CAD', 'AUD', 'EUR' ];

	for ( const testData of badgeTestsData ) {
		const { testKey, country, wooCommerceCountryCode } = testData;

		test( `${ testKey } | Settings - ${ country } - Onboarding - Badge values`, async ( {
			wooCommerceApi,
			pcpOnboarding,
			percy,
		}, testInfo ) => {
			for ( const currency of currencies ) {
				await wooCommerceApi.updateGeneralSettings( {
					woocommerce_default_country: wooCommerceCountryCode,
					woocommerce_currency: currency,
				} );
				await pcpOnboarding.visit();
				await pcpOnboarding.page.reload();
				await pcpOnboarding.gotoInitialOnboardingPage();
				await pcpOnboarding
					.badgeContainer()
					.waitFor( { state: 'visible' } );
				await pcpOnboarding.closeAdvancedOptions();
				await pcpOnboarding.page.waitForLoadState( 'load' );
				await percy.takeSnapshot(
					`${ testInfo.title } - PayPal Settings - ${ country } - ${ currency }`,
					percyPcpSettingsConfig
				);

				await pcpOnboarding.activatePayPalPaymentsButton().click();
				await pcpOnboarding.businessRadio().click();
				await pcpOnboarding.continueButton().click();
				await pcpOnboarding.virtualCheckbox().check();
				await pcpOnboarding.continueButton().click();
				await pcpOnboarding
					.disableOptionalPaymentMethodsRadio()
					.click();
				await pcpOnboarding.page.waitForLoadState( 'load' );
				await percy.takeSnapshot(
					`${ testInfo.title } - Choose checkout options - ${ country } - ${ currency }`,
					percyPcpSettingsConfig
				);
			}
		} );
	}

	test( 'PCP-4325 | Settings - DE - Onboarding - Badge values', async ( {
		wooCommerceApi,
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		for ( const currency of currencies ) {
			await wooCommerceApi.updateGeneralSettings( {
				woocommerce_default_country: 'DE:DE-BE',
				woocommerce_currency: currency,
			} );
			await pcpOnboarding.visit();
			await pcpOnboarding.page.reload();
			await pcpOnboarding.gotoInitialOnboardingPage();
			await pcpOnboarding
				.badgeContainer()
				.waitFor( { state: 'visible' } );
			await pcpOnboarding.closeAdvancedOptions();
			await pcpOnboarding.page.waitForLoadState( 'load' );
			await percy.takeSnapshot(
				`${ testInfo.title } - PayPal Settings - Germany - ${ currency }`,
				percyPcpSettingsConfig
			);

			await pcpOnboarding.activatePayPalPaymentsButton().click();
			await pcpOnboarding.virtualCheckbox().check();
			await pcpOnboarding.continueButton().click();
			await pcpOnboarding.disableOptionalPaymentMethodsRadio().click();
			await pcpOnboarding.page.waitForLoadState( 'load' );
			await percy.takeSnapshot(
				`${ testInfo.title } - Choose checkout options - Germany - ${ currency }`,
				percyPcpSettingsConfig
			);
		}
	} );
} );

test( 'PCP-4318 | Settings - US - Onboarding - Connect with business account, all product types, card payments enabled', async ( {
	pcpOnboarding,
	percy,
}, testInfo ) => {
	await pcpOnboarding.visit();
	await pcpOnboarding.activatePayPalPaymentsButton().click();
	await percy.takeSnapshot(
		`${ testInfo.title } - `,
		percyPcpSettingsConfig
	);

	await pcpOnboarding.businessRadio().click();
	await percy.takeSnapshot(
		`${ testInfo.title } - Set up store type`,
		percyPcpSettingsConfig
	);
	await pcpOnboarding.continueButton().click();
	await percy.takeSnapshot(
		`${ testInfo.title } - Select product types - No option selected`,
		percyPcpSettingsConfig
	);

	await pcpOnboarding.physicalGoodsCheckbox().check();
	await pcpOnboarding.virtualCheckbox().check();
	await percy.takeSnapshot(
		`${ testInfo.title } - Select product types - Products selected `,
		percyPcpSettingsConfig
	);
	await pcpOnboarding.continueButton().click();
	await percy.takeSnapshot(
		`${ testInfo.title } - Choose checkout options`,
		percyPcpSettingsConfig
	);
	await pcpOnboarding.disableOptionalPaymentMethodsRadio().click();
	await percy.takeSnapshot(
		`${ testInfo.title } - Choose checkout options - Card payments disabled`,
		percyPcpSettingsConfig
	);
} );
