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

test( 'PCP-4362 | Settings - Onboarding - See advanced options - Manually connect with sandbox account @Critical @Smoke', async ( {
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
	// Wait for account-type step (can take a moment to render)
	await expect(
		pcpOnboarding.businessRadio(),
		'Assert business radio is visible before selecting'
	).toBeVisible();

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

	await expect(
		pcpOnboarding.sandboxClientIdInput(),
		'Assert sandbox client ID input is visible'
	).toBeVisible();
	await pcpOnboarding.sandboxClientIdInput().fill( clientId );
	await pcpOnboarding.page.waitForTimeout( 1000 );

	await expect(
		pcpOnboarding.sandboxSecretKeyInput(),
		'Assert sandbox secret key input is visible'
	).toBeVisible();
	await pcpOnboarding.sandboxSecretKeyInput().fill( clientSecret );
	await pcpOnboarding.page.waitForTimeout( 1000 );

	await expect(
		pcpOnboarding.connectAccountButton(),
		'Assert connect account button is visible'
	).toBeVisible();
	await pcpOnboarding.connectAccountButton().click();

	await expect(
		pcpOverview.overviewTab(),
		'Assert overview tab is visible after connect'
	).toBeVisible();
	await expect(
		pcpOverview.settingsTab(),
		'Assert settings tab is visible'
	).toBeVisible();

	await pcpOverview.settingsTab().click();

	await expect(
		pcpSettings.connectionDetailsContainer(),
		'Assert connection details container is visible'
	).toBeVisible();
	await expect(
		pcpSettings.connectionDetailsContainer(),
		`Assert connection details contain account ID ${ accountId }`
	).toContainText( accountId );
	await expect(
		pcpSettings.connectionDetailsContainer(),
		`Assert connection details contain email ${ email }`
	).toContainText( email );
	await expect(
		pcpSettings.connectionDetailsContainer(),
		'Assert connection details contain client ID'
	).toContainText( clientId );
} );
