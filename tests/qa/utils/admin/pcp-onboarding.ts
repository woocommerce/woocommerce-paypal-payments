/**
 * Internal dependencies
 */
import { Pcp } from '../../resources';
import { PcpAdminPage } from './pcp-admin-page';
import urls from '../urls';
/**
 * External dependencies
 */
import { expect, Locator } from 'playwright/test';

export class PcpOnboarding extends PcpAdminPage {
	url = urls.admin.pcp.onboarding;

	// Locators
	saveAndExitButton = () =>
		this.navigationPanel().getByRole( 'button', { name: 'Save and exit' } );
	continueButton = () =>
		this.navigationPanel().getByRole( 'button', { name: 'Continue' } );

	onboardingContentContainer = () =>
		this.page.locator(
			'.ppcp-r-container.ppcp-r-container--card.ppcp-r-container--onboarding'
		);
	sendOnlyMessageHeading = () =>
		this.page.getByRole( 'heading', { name: '"Send-only" Country' } );
	activatePayPalPaymentsButton = () =>
		this.page.getByRole( 'button', { name: 'Activate PayPal Payments' } );

	advancedOptionsSection = () => this.page.locator( '#advanced-options' );
	seeAdvancedOptionsButton = () =>
		this.advancedOptionsSection().getByRole( 'button', {
			name: 'See advanced options',
		} );
	advancedOptionsContent = () =>
		this.advancedOptionsSection().locator( '.ppcp--accordion-content' );

	selectBoxContentContainer = () => this.page.locator( '.ppcp--box-content' );
	selectBoxContentDetail = () =>
		this.selectBoxContentContainer().locator( '.ppcp--box-details p' );

	businessRadio = () =>
		this.page.locator( 'input.ppcp-r__radio-value[value="business"]' );
	personalAccountRadio = () =>
		this.page.locator( 'input.ppcp-r__radio-value[value="casual_seller"]' );
	personalAccountContentDetail = () =>
		this.selectBoxContentContainer().locator(
			this.selectBoxContentDetail()
		);

	virtualCheckbox = () =>
		this.page.locator( 'input[type="checkbox"][value="virtual"]' );
	physicalGoodsCheckbox = () =>
		this.page.locator( 'input[type="checkbox"][value="physical"]' );
	subscriptionsCheckbox = () =>
		this.page.locator( 'input[type="checkbox"][value="subscriptions"]' );
	subscriptionsContentDetail = () =>
		this.selectBoxContentContainer().locator(
			this.selectBoxContentDetail()
		);

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

	sandboxClientIdInput = () => this.page.getByLabel( 'Sandbox Client ID' );
	sandboxSecretKeyInput = () => this.page.getByLabel( 'Sandbox Secret Key' );
	connectAccountButton = () =>
		this.page.getByRole( 'button', { name: 'Connect Account' } ).last();

	enableSandboxModeLabel = () => this.page.getByText( 'Enable Sandbox Mode' );
	enableSandboxModeToggle = () =>
		this.page.locator( '.components-form-toggle' ).first();
	/** First pricing badge on the page (PayPal Checkout: checkout % + fixed fee from countryPriceInfo). */
	badgeContainer = () =>
		this.page
			.locator( 'span.ppcp-r-title-badge.ppcp-r-title-badge--pricing' )
			.first();
	welcomeDocsContainer = () =>
		this.page.locator( '.ppcp-r-welcome-docs__wrapper' ).last();
	checkoutAlternativeOptionsContainer = () =>
		this.page
			.locator( '.ppcp-r-select-box' )
			.first()
			.locator( '.ppcp--box-content' );

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
		await expect(
			this.seeAdvancedOptionsButton(),
			'Assert See advanced options button is visible'
		).toBeVisible();
		if ( ! ( await this.advancedOptionsContent().isVisible() ) ) {
			await this.seeAdvancedOptionsButton().click();
			await this.advancedOptionsContent().waitFor( { state: 'visible' } );
		}
	};

	closeAdvancedOptions = async () => {
		await expect(
			this.seeAdvancedOptionsButton(),
			'Assert See advanced options button is visible'
		).toBeVisible();
		if ( await this.advancedOptionsContent().isVisible() ) {
			await this.seeAdvancedOptionsButton().click();
		}
	};

	toggleManuallyConnect = async ( enable: boolean ) => {
		await this.enableConnectionOptionsToogle(
			this.enableManuallyConnectToggle(),
			this.enableManuallyConnectLabel(),
			enable
		);
	};

	toggleSandboxMode = async ( enable: boolean ) => {
		await this.enableConnectionOptionsToogle(
			this.enableSandboxModeToggle(),
			this.enableSandboxModeLabel(),
			enable
		);
	};

	enableConnectionOptionsToogle = async (
		toggleLocator: Locator,
		labelLocator: Locator,
		enable: boolean
	) => {
		await expect( toggleLocator, 'Assert connection option toggle is visible' ).toBeVisible();
		await expect( labelLocator, 'Assert connection option label is visible' ).toBeVisible();

		const isChecked = await toggleLocator.getAttribute( 'class' );
		const isToggleChecked = isChecked.includes( 'is-checked' );

		if ( isToggleChecked !== enable ) {
			await labelLocator.click();
			await this.page.waitForLoadState( 'networkidle' );
		}
	};

	chooseOptionalPaymentMethods = async ( enable: boolean ) => {
		if ( enable ) {
			await this.enableOptionalPaymentMethodsRadio().check();
		} else {
			await this.disableOptionalPaymentMethodsRadio().check();
		}
	};

	// Assertions
	/**
	 * verifies if there is no warning on the console about badgeBoxUtils.js
	 */
	assertNoBadgeBoxUtilsWarnings = async () => {
		// stores console messages
		const consoleMessages: Array< { type: string; text: string } > = [];

		// Listens for console messages
		const listener = ( msg: any ) => {
			consoleMessages.push( {
				type: msg.type(),
				text: msg.text(),
			} );
		};
		this.page.on( 'console', listener );
		await this.page.waitForTimeout( 500 );

		this.page.removeListener( 'console', listener );

		const badgeBoxWarnings = consoleMessages.filter(
			( msg ) =>
				msg.type === 'warning' &&
				msg.text.includes( 'badgeBoxUtils.js' )
		);

		// return true if there is no warning
		return badgeBoxWarnings.length === 0;
	};
}
