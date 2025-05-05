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
		this.page.getByRole( 'button', {
			name: 'Toggle to manual credential input',
		} );
	documentationLink = () =>
		this.page.getByRole( 'link', { name: 'documentation', exact: true } );

	sandboxCheckbox = () => this.page.getByLabel( 'Sandbox', { exact: true } );

	liveEmailAddressInput = () => this.page.getByLabel( 'Live Email address' );
	liveMerchantIdInput = () => this.page.getByLabel( 'Live Merchant Id' );
	liveClientIdInput = () => this.page.getByLabel( 'Live Client Id' );
	liveSecretKeyInput = () =>
		this.page.locator( 'input[name="ppcp\\[client_secret_production\\]"]' );

	sandboxEmailAddressInput = () =>
		this.page.getByLabel( 'Sandbox Email address' );
	sandboxMerchantIdInput = () =>
		this.page.getByLabel( 'Sandbox Merchant Id' );
	sandboxClientIdInput = () => this.page.getByLabel( 'Sandbox Client Id' );
	sandboxSecretKeyInput = () =>
		this.page.locator( 'input[name="ppcp\\[client_secret_sandbox\\]"]' );

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
	 * Checks if merchant is connected
	 *
	 * @param data
	 */
	isMerchantConnected = async ( data? ) => {
		if ( ! ( await this.disconnectAccountButton().isVisible() ) ) {
			return false;
		}

		const emailInput = this.sandboxEmailAddressInput();
		const merchantIdInput = this.sandboxMerchantIdInput();
		const clientIdInput = this.sandboxClientIdInput();
		const secretKeyInput = this.sandboxSecretKeyInput();

		const emailInputValue = ( await emailInput.inputValue() ).trim();
		const merchantIdValue = ( await merchantIdInput.inputValue() ).trim();
		const clientIdValue = ( await clientIdInput.inputValue() ).trim();
		const secretKeyValue = ( await secretKeyInput.inputValue() ).trim();

		if ( ! data ) {
			return (
				emailInputValue !== '' &&
				merchantIdValue !== '' &&
				clientIdValue !== '' &&
				secretKeyValue !== ''
			);
		}

		return (
			emailInputValue === data.email &&
			merchantIdValue === data.account_id &&
			clientIdValue === data.client_id &&
			secretKeyValue === data.client_secret
		);
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
		if ( options.enablePayUponInvoice ) {
			await this.onboardPayUponInvoiceCheckbox().check();
		} else if ( await this.onboardPayUponInvoiceCheckbox().isVisible() ) {
			await this.onboardPayUponInvoiceCheckbox().uncheck();
		}

		await this.toggleToManualCredentialInputButton().click();
		await this.sandboxCheckbox().check();
		await this.sandboxEmailAddressInput().fill( merchant.email );
		await this.sandboxMerchantIdInput().fill( merchant.account_id );
		await this.sandboxClientIdInput().fill( merchant.client_id );
		await this.sandboxSecretKeyInput().fill( merchant.client_secret );
		await this.saveChangesButton().click();
		await this.page.waitForLoadState();
		// make sure Connection page has been loaded:
		await this.disconnectAccountButton().waitFor( { state: 'visible' } );
		await this.updateInvoicePrefix();
	};

	updateInvoicePrefix = async (
		prefix: string = generateRandomString( 10 )
	) => {
		await this.invoicePrefixInput().fill( `${ prefix }-` );
		await this.saveChanges();
	};

	/**
	 * Disconnects PayPal merchant
	 */
	disconnectMerchant = async () => {
		const disconnectButton = this.disconnectAccountButton();

		if ( await disconnectButton.isVisible() ) {
			await disconnectButton.click();
		}
		await this.page.waitForLoadState();
		// make sure Account Setup page has been loaded:
		await this.toggleToManualCredentialInputButton().waitFor( {
			state: 'visible',
		} );
	};

	clearDB = async () => {
		this.page.on( 'dialog', ( dialog ) => dialog.accept() );
		await this.clearNowButton().click();
		await this.page.waitForLoadState();
		// make sure Account Setup page has been loaded:
		await this.toggleToManualCredentialInputButton().waitFor( {
			state: 'visible',
		} );
	};

	// Assertions
}
