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
import { badgeValuesPerCountry } from './.test-scenarios';
import { countries, currencies } from './.test-data/badges-per-country';

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

test.describe.only(
	'PCP-0000 | Settings - Onboarding - Badge values per country @percy',
	() => {
		const currencies = [ 'USD', 'GBP', 'CAD', 'AUD', 'EUR' ];

		for ( const { country, label } of countries ) {
			test( `PCP-0000 | Settings - ${ label } - Onboarding - Badge values for ${ country }`, async ( {
				wooCommerceApi,
				pcpOnboarding,
				percy,
			}, testInfo ) => {
				await badgeValuesPerCountry(
					{ wooCommerceApi, pcpOnboarding, percy },
					testInfo,
					country,
					label,
					currencies
				);
			} );
		}
	}
);
