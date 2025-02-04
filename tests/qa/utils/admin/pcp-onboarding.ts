/**
 * Internal dependencies
 */
import { PcpMerchant, PcpSettings } from '../../resources';
import { PcpSettingsPage } from './pcp-settings-page';
import urls from '../urls';

export class PcpOnboarding extends PcpSettingsPage {
	url = urls.pcp.onboarding;

	// Locators
	navigationPanel = () => this.page.locator( '.ppcp-r-navigation' );
	backButton = () => this.navigationPanel().locator( 'button.is-title' );
	onboardingPageTitle = ( title: PcpSettings.OnboardingStepTitle ) =>
		this.backButton().getByText( title );
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
		this.page.locator(
			'input[name="business"][value="business"]'
		);
	personalAccountRadio = () =>
		this.page.locator(
			'input[name="business"][value="casual_seller"]'
		);

	virtualCheckbox = () =>
		this.page.locator( 'input[type="checkbox"][value="virtual"]' );
	physicalGoodsCheckbox = () =>
		this.page.locator( 'input[type="checkbox"][value="physical"]' );

	enableOptionalPaymentMethodsRadio = () =>
		this.page.locator(
			'input[name="optional-payment-methods"][value="true"]'
		);
	disableOptionalPaymentMethodsRadio = () =>
		this.page.locator(
			'input[name="optional-payment-methods"][value="false"]'
		);

	connectToPayPalButton = () =>
		this.page.getByRole( 'button', { name: 'Connect to PayPal' } );


	// Actions
	isCurrentStep = async ( title: PcpSettings.OnboardingStepTitle ) => {
		await this.page.waitForFunction(() => !!document.querySelector('button.is-title'));
		return await this.onboardingPageTitle( title ).isVisible();
	};

	gotoInitialOnboardingPage = async () => {
		if ( await this.isCurrentStep( 'PayPal Payments' ) ) {
			return;
		}
		await this.backButton().click();
		await this.gotoInitialOnboardingPage();
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

	// Assertions
}
