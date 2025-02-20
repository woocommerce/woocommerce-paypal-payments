/**
 * Internal dependencies
 */
import { PayPalAccount, Pcp } from '../../resources';
import { PcpAdminPage, PayPalConnectionPopup } from '.';
import urls from '../urls';
import { expect } from 'playwright/test';

export class PcpOnboarding extends PcpAdminPage {
	url = urls.admin.pcp.onboarding;

	// Locators
	saveAndExitButton = () =>
		this.navigationPanel().getByRole( 'button', { name: 'Save and exit' } );
	continueButton = () =>
		this.navigationPanel().getByRole( 'button', { name: 'Continue' } );

	activatePayPalPaymentsButton = () =>
		this.page.getByRole( 'button', { name: 'Activate PayPal Payments' } );

	advancedOptionsSection = () => this.page.locator( '#advanced-options' );
	seeAdvancedOptionsButton = () =>
		this.advancedOptionsSection().getByRole( 'button', {
			name: 'See advanced options',
		} );
	advancedOptionsContent = () =>
		this.advancedOptionsSection().locator( '.ppcp-r-accordion__content' );

	businessRadio = () =>
		this.page.locator( 'input.ppcp-r__radio-value[value="business"]' );
	personalAccountRadio = () =>
		this.page.locator( 'input.ppcp-r__radio-value[value="casual_seller"]' );

	virtualCheckbox = () =>
		this.page.locator( 'input[type="checkbox"][value="virtual"]' );
	physicalGoodsCheckbox = () =>
		this.page.locator( 'input[type="checkbox"][value="physical"]' );

	enableOptionalPaymentMethodsRadio = () =>
		this.page.locator( 'input.ppcp-r__radio-value[value="true"]' );

	disableOptionalPaymentMethodsRadio = () =>
		this.page.locator( 'input.ppcp-r__radio-value[value="false"]' );

	connectToPayPalButton = () =>
		this.page.getByRole( 'button', { name: 'Connect to PayPal' } );

	enableManuallyConnectLabel = () =>
		this.page.getByText( 'Manually Connect' );

	enableManuallyConnectToggle = () =>
		this.page.locator( '.components-form-toggle' ).nth( 1 );

	enableSandboxModeLabel = () => this.page.getByText( 'Enable Sandbox Mode' );
	enableSandboxModeToggle = () =>
		this.page.locator( '.components-form-toggle' ).first();
	badgeContainer = () =>
		this.page
			.locator( 'span.ppcp-r-title-badge.ppcp-r-title-badge--info' )
			.last();

	enableSandboxModeContainer = () =>
		this.page.locator( '.ppcp-r-busy-wrapper', {
			has: this.enableSandboxModeLabel(),
		} );

	connectButton = () =>
		this.enableSandboxModeContainer().getByText( 'Connect Account' );

	popupEmail = () => this.page.locator( '#email' );
	popupContinueButton = () => this.page.locator( '#continueButton' );
	popupPassword = () => this.page.locator( '#password' );
	popupLoginButton = () => this.page.locator( '#btnLogin' );

	// Actions
	isCurrentStep = async ( title: Pcp.Admin.Onboarding.StepTitle ) => {
		await this.backButton().waitFor( { state: 'visible' } );
		return await this.pageTitle( String( title ) ).isVisible();
	};

	gotoInitialOnboardingPage = async () => {
		if ( ! ( await this.isCurrentStep( 'PayPal Payments' ) ) ) {
			await this.backButton().click();
			await this.page.waitForLoadState();
			await this.gotoInitialOnboardingPage();
		}
	};

	openAdvancedOptions = async () => {
		await this.gotoInitialOnboardingPage();
		if ( ! ( await this.advancedOptionsContent().isVisible() ) ) {
			await this.seeAdvancedOptionsButton().click();
		}
	};

	closeAdvancedOptions = async () => {
		await this.gotoInitialOnboardingPage();
		if ( await this.advancedOptionsContent().isVisible() ) {
			await this.seeAdvancedOptionsButton().click();
		}
	};

	enableManuallyConnect = async () => {
		const isChecked = await this.enableManuallyConnectToggle().getAttribute(
			'class'
		);
		const isToggleChecked = isChecked.includes( 'is-checked' );
		if ( ! isToggleChecked ) {
			await this.enableManuallyConnectLabel().click();
			await this.page.waitForLoadState( 'networkidle' );
		}
	};

	enableSandboxMode = async () => {
		const isChecked = await this.enableSandboxModeToggle().getAttribute(
			'class'
		);
		const isToggleChecked = isChecked.includes( 'is-checked' );
		if ( ! isToggleChecked ) {
			await this.enableSandboxModeLabel().click();
			await this.page.waitForLoadState( 'networkidle' );
		}
	};

	openConnectionPopup = async () => {
		const popupPromise = this.page.waitForEvent( 'popup' );
		await this.connectButton().click();
		const popup = await popupPromise;
		await popup.waitForLoadState();
		return new PayPalConnectionPopup( { popup } );
	};

	connectToSandbox = async ( paypalUser: PayPalAccount ) => {
		const popup = await this.openConnectionPopup();
		await popup.login( paypalUser.email, paypalUser.password);
		await this.page.waitForLoadState( 'networkidle' );
		await expect( this.page ).toHaveURL( urls.admin.pcp.overview );
	  };

	// Assertions
}
