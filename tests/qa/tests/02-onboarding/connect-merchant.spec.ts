/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import { storeConfigDefault, merchants } from '../../resources';

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
} );

test( 'PCP-4362 | Settings - Onboarding - See advanced options - Manually connect with sandbox account @Critical', async ( {
	pcpOnboarding,
	pcpOverview,
	pcpSettings,
}, testInfo ) => {
	const { account_id, client_id, client_secret, email } = merchants.usa;

	await pcpOnboarding.visit();
	await pcpOnboarding.gotoInitialOnboardingPage();
	await pcpOnboarding.activatePayPalPaymentsButton().click();

	await pcpOnboarding.businessRadio().click();
	await pcpOnboarding.continueButton().click();

	await pcpOnboarding.physicalGoodsCheckbox().check();
	await pcpOnboarding.virtualCheckbox().check();
	await pcpOnboarding.continueButton().click();

	await pcpOnboarding.enableOptionalPaymentMethodsRadio().click();
	await pcpOnboarding.gotoInitialOnboardingPage();
	
	await pcpOnboarding.openAdvancedOptions();
	await pcpOnboarding.toggleSandboxMode( true );
	await pcpOnboarding.toggleManuallyConnect( true );
	
	await expect( pcpOnboarding.sandboxClientIdInput() ).toBeVisible();
	await pcpOnboarding.sandboxClientIdInput().fill( client_id );
	await pcpOnboarding.page.waitForTimeout( 1000 );

	await expect( pcpOnboarding.sandboxSecretKeyInput() ).toBeVisible();
	await pcpOnboarding.sandboxSecretKeyInput().fill( client_secret );
	await pcpOnboarding.page.waitForTimeout( 1000 );

	await expect( pcpOnboarding.connectAccountButton() ).toBeVisible();
	await pcpOnboarding.connectAccountButton().click();

	await expect( pcpOverview.overviewTab() ).toBeVisible();
	await expect( pcpOverview.settingsTab() ).toBeVisible();
	
	await pcpOverview.settingsTab().click();
	
	await expect( pcpSettings.connectionDetailsContainer() ).toBeVisible();
	await expect( pcpSettings.connectionDetailsContainer() ).toContainText( account_id );
	await expect( pcpSettings.connectionDetailsContainer() ).toContainText( email );
	await expect( pcpSettings.connectionDetailsContainer() ).toContainText( client_id );
} );
