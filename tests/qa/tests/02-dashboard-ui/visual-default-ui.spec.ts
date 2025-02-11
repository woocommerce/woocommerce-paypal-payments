/**
 * External dependencies
 */
import { shopSettings } from '@inpsyde/playwright-utils/build';
import { PercyConfig } from '@inpsyde/playwright-utils/build/@types/visual/percy';
/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { storeConfigDefault } from '../../resources';

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

test( 'PCP-0000 | Settings - Badge values per country @percy', async ( {
	wooCommerceApi,
	pcpOnboarding,
	percy,
}, testInfo ) => {
	const countries = [ 'germany', 'usa' ];

	await pcpOnboarding.visit();

	for ( const country of countries ) {
		const generalCountrySettings = shopSettings[ country ].general;
		await wooCommerceApi.updateGeneralSettings( generalCountrySettings );
		await pcpOnboarding.page.reload();
		await pcpOnboarding.gotoInitialOnboardingPage();
		await pcpOnboarding.page.waitForSelector(
			'span.ppcp-r-title-badge.ppcp-r-title-badge--info'
		);
		await pcpOnboarding.closeAdvancedOptions();
		await percy.takeSnapshot(
			`${ testInfo.title } - PayPal Settings - ${ country }`,
			percyConfig
		);

		await pcpOnboarding.activatePayPalPaymentsButton().click();
		if ( country === 'usa' ) {
			await pcpOnboarding.businessRadio().click();
			await pcpOnboarding.continueButton().click();
		}
		await pcpOnboarding.virtualCheckbox().check();
		await pcpOnboarding.continueButton().click();
		await pcpOnboarding.disableOptionalPaymentMethodsRadio().click();
		await percy.takeSnapshot(
			`${ testInfo.title } - Choose checkout options - ${ country }`,
			percyConfig
		);
	}
} );
