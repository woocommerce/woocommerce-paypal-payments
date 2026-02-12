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
} ) => {
	const {
		account_id: accountId,
		client_id: clientId,
		client_secret: clientSecret,
		email,
	} = merchants.usa;

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
	await pcpOnboarding.sandboxClientIdInput().fill( clientId );
	await pcpOnboarding.page.waitForTimeout( 1000 );

	await expect( pcpOnboarding.sandboxSecretKeyInput() ).toBeVisible();
	await pcpOnboarding.sandboxSecretKeyInput().fill( clientSecret );
	await pcpOnboarding.page.waitForTimeout( 1000 );

	await expect( pcpOnboarding.connectAccountButton() ).toBeVisible();
	await pcpOnboarding.connectAccountButton().click();

	await expect( pcpOverview.overviewTab() ).toBeVisible();
	await expect( pcpOverview.settingsTab() ).toBeVisible();

	await pcpOverview.settingsTab().click();

	await expect( pcpSettings.connectionDetailsContainer() ).toBeVisible();
	await expect( pcpSettings.connectionDetailsContainer() ).toContainText(
		accountId
	);
	await expect( pcpSettings.connectionDetailsContainer() ).toContainText(
		email
	);
	await expect( pcpSettings.connectionDetailsContainer() ).toContainText(
		clientId
	);
} );
