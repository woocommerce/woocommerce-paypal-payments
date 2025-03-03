/**
 * External dependencies
 */
import { PercyConfig } from '@inpsyde/playwright-utils/build/@types/visual/percy';
/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { storeConfigDefault } from '../../resources';
import {
	badgeTestsData,
	initialOnboardingScreenData,
} from './_test-data/badges-per-country.data';

const percyConfig: PercyConfig = {
	scope: '#ppcp-settings-container',
	httpCredentials: {
		username: process.env.WP_BASIC_AUTH_USER,
		password: process.env.WP_BASIC_AUTH_PASS,
	},
};

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.configurePcp( {
		disconnectMerchant: true,
	} );
} );

test.describe.serial( () => {
	test( 'PCP-0000 | Settings - Onboarding initial page - Default UI @percy', async ( {
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.gotoInitialOnboardingPage();
		await pcpOnboarding.page.waitForLoadState();
		await percy.takeSnapshot( testInfo.title, percyConfig );
	} );

	test( 'PCP-0000 | Settings - Onboarding initial page - See advanced options - Default UI @percy', async ( {
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.openAdvancedOptions();
		await percy.takeSnapshot( testInfo.title, percyConfig );
	} );

	test( 'PCP-0000 | Settings - Onboarding - Select product types - Default UI @percy', async ( {
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.activatePayPalPaymentsButton().click();
		await pcpOnboarding.page.waitForLoadState();
		await percy.takeSnapshot( testInfo.title, percyConfig );
	} );

	test( 'PCP-0000 | Settings - Onboarding - Choose checkout options - Default UI @percy', async ( {
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.virtualCheckbox().check();
		await pcpOnboarding.continueButton().click();
		await pcpOnboarding.page.waitForLoadState();
		await percy.takeSnapshot( testInfo.title, percyConfig );
	} );

	test( 'PCP-0000 | Settings - Onbarding - Connect your PayPal account - Default UI @percy', async ( {
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.disableOptionalPaymentMethodsRadio().click();
		await pcpOnboarding.continueButton().click();
		await pcpOnboarding.page.waitForLoadState();
		await percy.takeSnapshot( testInfo.title, percyConfig );
	} );

	test( 'PCP-0000 | Settings - Onboarding - Enable Sandbox mode - Default UI @percy', async ( {
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.openAdvancedOptions();
		await pcpOnboarding.enableSandboxMode();
		await percy.takeSnapshot( testInfo.title, percyConfig );
	} );

	test( 'PCP-0000 | Settings - Onboarding - See advanced options - Manually Connect by clicking on label - Default UI @percy', async ( {
		pcpOnboarding,
		percy,
	}, testInfo ) => {
		await pcpOnboarding.visit();
		await pcpOnboarding.openAdvancedOptions();
		await pcpOnboarding.enableManuallyConnect();
		await percy.takeSnapshot( testInfo.title, percyConfig );
	} );
} );

test.describe( () => {
	for ( const data of initialOnboardingScreenData ) {
		test( `${ data.testSummary }`, async ( {
			pcpOnboarding,
			percy,
			wooCommerceApi,
		}, testInfo ) => {
			await wooCommerceApi.updateGeneralSettings(
				data.wooCommerceGeneralSettings
			);
			await pcpOnboarding.visit();
			await pcpOnboarding.gotoInitialOnboardingPage();
			await percy.takeSnapshot( testInfo.title, percyConfig );
		} );
	}
} );

test.describe( () => {
	const currencies = [ 'USD', 'GBP', 'CAD', 'AUD', 'EUR' ];

	for ( const testData of badgeTestsData ) {
		const { testKey, countryCode, wooCommerceCountryCode } = testData;

		test( `${ testKey } | Settings - ${ countryCode } - Onboarding - Badge values`, async ( {
			wooCommerceApi,
			pcpOnboarding,
			percy,
		}, testInfo ) => {
			await pcpOnboarding.visit();
			for ( const currency of currencies ) {
				await wooCommerceApi.updateGeneralSettings( {
					woocommerce_default_country: wooCommerceCountryCode,
					woocommerce_currency: currency,
				} );

				await pcpOnboarding.page.reload();
				await pcpOnboarding.gotoInitialOnboardingPage();
				await pcpOnboarding
					.badgeContainer()
					.waitFor( { state: 'visible' } );
				await pcpOnboarding.closeAdvancedOptions();
				await pcpOnboarding.page.waitForLoadState( 'load' );
				await percy.takeSnapshot(
					`${ testInfo.title } - PayPal Settings - ${ countryCode } - ${ currency }`,
					percyConfig
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
					`${ testInfo.title } - Choose checkout options - ${ countryCode } - ${ currency }`,
					percyConfig
				);
			}
		} );
	}
} );
