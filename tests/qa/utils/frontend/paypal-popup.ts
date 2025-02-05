/**
 * External dependencies
 */
import { expect, Page } from '@playwright/test';

export class PayPalPopup {
	popup: Page;

	constructor( popup ) {
		this.popup = popup;
	}

	// Locators
	loginWithPasswordInsteadLink = () =>
		this.popup.getByRole( 'link', {
			name: 'Log in with a password instead',
		} );
	loginInput = () => this.popup.locator( '[name="login_email"]' );
	passwordInput = () => this.popup.locator( '[name="login_password"]' );
	nextButton = () => this.popup.locator( '#btnNext' );
	loginButton = () => this.popup.locator( '#btnLogin' );
	submitPaymentButton = () => this.popup.locator( '#payment-submit-btn' );
	payLaterSwitcher = () => this.popup.getByTestId( 'paylater-tab' );
	payLaterRadio = () =>
		this.popup.locator( 'label[for^="credit-offer"]' ).first();
	saveAndContinueButton = () => this.popup.getByTestId( 'consentButton' );
	cancelLink = () => this.popup.locator( '#cancelLink' );

	// Actions

	login = async ( email, password ) => {
		await expect( this.popup ).toHaveTitle(
			'Log in to your PayPal account'
		);

		if (
			! ( await this.loginInput().isEditable() ) &&
			this.loginWithPasswordInsteadLink().isVisible()
		) {
			this.loginWithPasswordInsteadLink().click();
		}

		await this.loginInput().fill( email );
		// Sometimes we get a popup with email and password fields at the same screen
		if ( await this.nextButton().isVisible() ) {
			await this.nextButton().click();
		}
		await this.passwordInput().fill( password );
		await this.loginButton().click();
	};

	completePayment = async () => {
		await Promise.all( [
			this.popup.waitForEvent( 'close' ),
			this.submitPaymentButton().click(),
		] );
	};

	savePaymentMethodAndContinue = async () => {
		await Promise.all( [
			this.popup.waitForEvent( 'close' ),
			this.saveAndContinueButton().click(),
		] );
	};
}
