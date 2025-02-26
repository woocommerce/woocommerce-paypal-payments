/**
 * Internal dependencies
 */
import { Pcp } from '../../resources';
import { PcpAdminPage } from './pcp-admin-page';
import urls from '../urls';

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

	learnMoreLinks = () => this.page.locator( '.ppcp-r-welcome-docs a' );

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

	getLearnMoreLinks = async (): Promise<
		{ url: string; title: string }[]
	> => {
		await this.page.waitForSelector( '.ppcp-r-welcome-docs a', {
			state: 'visible',
		} );

		const links = await this.learnMoreLinks().all();

		const extractedLinks = [];
		for ( const link of links ) {
			const href = await link.getAttribute( 'href' );

			if ( href ) {
				const fullUrl = href.startsWith( 'http' )
					? href
					: new URL( href, 'https://www.paypal.com' ).href;

				extractedLinks.push( {
					url: fullUrl,
					title: await link.innerText(),
				} );
			}
		}
		console.log( 'extractedLinks:', extractedLinks );
		return extractedLinks;
	};

	clickLearnMoreLink = async ( linkUrl: string ) => {
		const link = this.page.locator( `a[href="${ linkUrl }"]` );

		const [ newPage ] = await Promise.all( [
			this.page.context().waitForEvent( 'page' ),
			link.first().click(),
		] );

		await newPage.waitForLoadState( 'domcontentloaded' );
		return newPage;
	};

	getPageTitle = async ( newPage = this.page ) => {
		return await newPage.title();
	};

	// Assertions
}
