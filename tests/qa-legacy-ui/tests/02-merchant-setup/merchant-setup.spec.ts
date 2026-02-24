/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	merchants,
	pcpConfigDefault,
	storeConfigDefault,
} from '../../resources';

test.describe( 'Merchant Setup', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( storeConfigDefault );
		await utils.configurePcp( {
			clearPCPDB: false,
			merchant: merchants.usa,
		} );
	} );

	test.beforeEach( async ( { pcpApi } ) => {
		await pcpApi.disconnectMerchant();
	} );

	test( 'PCP-2314 | Merchant Setup page UI @Critical', async ( {
		connection,
	} ) => {
		await connection.visit();
		// Wait for loading because there are additional elements which disappear after page is fully loaded (activatePayPalButton, testPaymentsWithPayPalSandboxButton)
		await connection.page.waitForLoadState( 'domcontentloaded' );
		await connection.assertUrl();
		await expect( connection.documentationButton() ).toBeVisible();
		await expect( connection.getHelpLink() ).toBeVisible();
		await expect( connection.enablePayPalPaymentsCheckbox() ).toBeVisible();
		await expect( connection.acceptCardsCheckbox() ).toBeVisible();
		// // Only for german merchant:
		// await expect(
		// 	connection.onboardPayUponInvoiceCheckbox()
		// ).toBeVisible();
		await expect( connection.activatePayPalButton() ).toBeVisible();
		await expect(
			connection.testPaymentsWithPayPalSandboxButton()
		).toBeVisible();
		await expect(
			connection.toggleToManualCredentialInputButton()
		).toBeVisible();
		await expect( connection.documentationLink() ).toBeVisible();

		await connection.toggleToManualCredentialInputButton().click();
		await expect( connection.sandboxCheckbox() ).toBeVisible();
		await expect( connection.liveEmailAddressInput() ).toBeVisible();
		await expect( connection.liveMerchantIdInput() ).toBeVisible();
		await expect( connection.liveClientIdInput() ).toBeVisible();
		await expect( connection.liveSecretKeyInput() ).toBeVisible();
		await expect( connection.saveChangesButton() ).toBeVisible();

		await connection.sandboxCheckbox().click();
		await expect( connection.sandboxEmailAddressInput() ).toBeVisible();
		await expect( connection.sandboxMerchantIdInput() ).toBeVisible();
		await expect( connection.sandboxClientIdInput() ).toBeVisible();
		await expect( connection.sandboxSecretKeyInput() ).toBeVisible();
		await expect( connection.saveChangesButton() ).toBeVisible();
	} );

	test( 'PCP-1920 | Documentation link @Critical', async ( {
		connection,
		context,
	} ) => {
		await connection.visit();
		const pagePromise = context.waitForEvent( 'page' );
		await connection.documentationLink().click();
		const newPage = await pagePromise;
		await newPage.waitForLoadState();
		await expect( newPage ).toHaveURL(
			'https://woocommerce.com/document/woocommerce-paypal-payments/#manual-credential-input'
		);
	} );

	test( 'PCP-1008 | Merchant Setup - Manual Credentials Input - Connect with valid credentials @Critical', async ( {
		connection,
		page,
	} ) => {
		await connection.visit();
		await connection.connectMerchant( merchants.usa );
		await expect( connection.disconnectAccountButton() ).toBeVisible();

		await expect(
			page.getByText(
				'Your account is connected to sandbox, no real charging takes place. To accept live payments, turn off sandbox mode and connect your live PayPal account.'
			)
		).toBeVisible();
	} );

	test( 'PCP-1032 | Merchant Setup - Manual Credentials Input - Invalid credentials @Critical', async ( {
		connection,
		page,
	} ) => {
		await connection.visit();
		await connection.connectMerchant( merchants.invalid );
		await expect( connection.disconnectAccountButton() ).toBeVisible();

		await expect(
			page.getByText(
				'Authentication with PayPal failed: Could not create token.'
			)
		).toBeVisible();
		await expect(
			page.getByText(
				'Please verify your API Credentials and try again to connect your PayPal business'
			)
		).toBeVisible();
		await expect(
			page.getByText( 'Failed to load webhooks.' )
		).toBeVisible();
	} );
} );
