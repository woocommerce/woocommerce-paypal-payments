/**
 * Internal dependencies
 */
import { PcpSettingsPage } from './pcp-settings-page';
import urls from '../urls';
/**
 * External dependencies
 */
import { PcpMerchant } from '../../resources';
import { generateRandomString } from '../helpers';
/**
 * External dependencies
 */
import { expect } from 'playwright/test';

export class Connection extends PcpSettingsPage {
	url = urls.pcp.connection;

	// Locators
	documentationButton = () =>
		this.page.getByRole( 'link', { name: 'Documentation', exact: true } );
	getHelpLink = () =>
		this.page.getByRole( 'link', { name: 'Get Help', exact: true } );

	enablePayPalPaymentsCheckbox = () =>
		this.page.getByLabel(
			'Enable PayPal Payments — includes PayPal, Venmo, Pay Later — with fraud protection'
		);
	acceptCardsCheckbox = () =>
		this.page.getByLabel(
			'Securely accept all major credit & debit cards on the strength of the PayPal network'
		);
	onboardPayUponInvoiceCheckbox = () =>
		this.page.getByLabel( 'Onboard with Pay upon Invoice' );

	activatePayPalButton = () =>
		this.page.getByRole( 'link', { name: 'Activate PayPal' } );
	testPaymentsWithPayPalSandboxButton = () =>
		this.page.getByRole( 'link', {
			name: 'Test payments with PayPal sandbox',
		} );
	toggleToManualCredentialInputButton = () =>
		this.page.locator( 'button[id="ppcp[toggle_manual_input]"]' );
	documentationLink = () =>
		this.page.getByRole( 'link', { name: 'documentation', exact: true } );

	sandboxCheckbox = () => this.page.locator( '#ppcp-sandbox_on' );

	liveEmailAddressInput = () => this.page.getByLabel( 'Live Email address' );
	liveMerchantIdInput = () => this.page.getByLabel( 'Live Merchant Id' );
	liveClientIdInput = () => this.page.getByLabel( 'Live Client Id' );
	liveSecretKeyInput = () =>
		this.page.locator( 'input[name="ppcp\\[client_secret_production\\]"]' );

	sandboxEmailAddressInput = () =>
		this.page.locator( '#ppcp-merchant_email_sandbox' );
	sandboxMerchantIdInput = () =>
		this.page.locator( '#ppcp-merchant_id_sandbox' );
	sandboxClientIdInput = () => this.page.locator( '#ppcp-client_id_sandbox' );
	sandboxSecretKeyInput = () =>
		this.page.locator( 'input[name="ppcp[client_secret_sandbox]"]' );

	statusConnected = () => this.page.getByText( 'Status: Connected' );
	disconnectAccountButton = () =>
		this.page.getByRole( 'button', { name: 'Disconnect Account' } );
	removeDataOnUninstallCheckbox = () =>
		this.page.getByLabel(
			'Remove PayPal Payments data from Database on uninstall'
		);
	clearNowButton = () =>
		this.page.getByRole( 'button', { name: 'Clear now' } );
	advancedCardPaymentRow = () =>
		this.page.locator( '#field-ppcp_dcc_status' );
	advancedCardPaymentStatus = () =>
		this.advancedCardPaymentRow().getByText( 'Status: Available' );
	advancedCardPaymentSettingsButton = () =>
		this.advancedCardPaymentRow().getByRole( 'link', { name: 'Settings' } );
	simulateButton = () =>
		this.page.getByRole( 'button', { name: 'Simulate' } );
	webhooksList = () =>
		this.page.locator( '#field-webhooks_list tbody > tr > td' );
	resubscribeButton = () =>
		this.page.getByRole( 'button', { name: 'Resubscribe' } );

	invoicePrefixInput = () => this.page.locator( '#ppcp-prefix' );

	// Actions

	/**
	 * Bulk update of tab settings
	 *
	 * @param data
	 */
	setup = async ( data? ) => {
		if ( ! data ) {
			return;
		}

		await this.visit();

		// Add settings here

		await this.saveChanges();
	};

	/**
	 * Connects PayPal merchant with options
	 *
	 * @param merchant
	 * @param options
	 */
	connectMerchant = async (
		merchant: PcpMerchant,
		options = {
			enablePayUponInvoice: false,
		}
	) => {
		await this.page.waitForLoadState();
		if ( options.enablePayUponInvoice ) {
			await this.onboardPayUponInvoiceCheckbox().check();
		} else if ( await this.onboardPayUponInvoiceCheckbox().isVisible() ) {
			await this.onboardPayUponInvoiceCheckbox().uncheck();
		}

		const toggleToManualCredentialInputButton =
			this.toggleToManualCredentialInputButton();
		await expect( toggleToManualCredentialInputButton ).toBeVisible();
		await toggleToManualCredentialInputButton.click( { force: true } );
		
		await this.sandboxCheckbox().click( { force: true } ); // this is not a regular checkbox element

		const sandboxEmailAddressInput = this.sandboxEmailAddressInput();
		await expect( sandboxEmailAddressInput ).toBeVisible();
		await sandboxEmailAddressInput.fill( merchant.email );

		const sandboxMerchantIdInput = this.sandboxMerchantIdInput();
		await expect( sandboxMerchantIdInput ).toBeVisible();
		await sandboxMerchantIdInput.fill( merchant.account_id );

		const sandboxClientIdInput = this.sandboxClientIdInput();
		await expect( sandboxClientIdInput ).toBeVisible();
		await sandboxClientIdInput.fill( merchant.client_id );

		const sandboxSecretKeyInput = this.sandboxSecretKeyInput();
		await expect( sandboxSecretKeyInput ).toBeVisible();
		await sandboxSecretKeyInput.fill( merchant.client_secret );

		const saveChangesButton = this.saveChangesButton();
		await expect( saveChangesButton ).toBeVisible();
		await saveChangesButton.click();
		await this.page.waitForLoadState();
		// make sure Connection page has been loaded:

		await expect( this.disconnectAccountButton() ).toBeVisible();
		await this.updateInvoicePrefix();
	};

	updateInvoicePrefix = async (
		prefix: string = generateRandomString( 10 )
	) => {
		await this.invoicePrefixInput().fill( `${ prefix }-` );
		await this.saveChanges();
	};

	// Assertions
}
