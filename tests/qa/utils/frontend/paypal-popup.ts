/**
 * External dependencies
 */
import { expect, Page } from '@playwright/test';
/**
 * Internal dependencies
 */
import { PayPalAccount } from '../../resources';

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
	submitPaymentButton = () =>
		this.popup
			.locator( '#payment-submit-btn' )
			.or( this.popup.getByTestId( 'submit-button-initial' ) )
			.or( this.popup.getByTestId( 'consentButton' ) )
			.or( this.popup.getByRole( 'button', { name: 'Continue' } ) )
			.or( this.popup.locator( '#confirmButtonTop' ) );
	payLaterSwitcher = () => this.popup.getByTestId( 'paylater-tab' );
	payLaterRadio = () =>
		this.popup.locator( 'label[for^="credit-offer"]' ).first();
	venmoButton = () => this.popup.locator( '.venmo-button-wrapper>button' );
	saveAndContinueButton = () => this.popup.getByTestId( 'consentButton' );
	cancelLink = () => this.popup.locator( '#cancelLink' );
	loadSpinnerContainer = () => this.popup.locator( '#preloaderSpinner' );

	// Actions

	login = async ( email, password ) => {
		await this.popup.waitForLoadState();
		await expect( this.loadSpinnerContainer() ).not.toBeVisible();
		// Sometimes the phone may be requested
		if (
			! ( await this.loginInput().isEditable() ) &&
			await this.loginWithPasswordInsteadLink().isVisible()
		) {
			await this.loginWithPasswordInsteadLink().click();
		}

		await this.loginInput().fill( email );

		// Sometimes we get a popup with email and password fields at the same screen
		if ( await this.nextButton().isVisible() ) {
			await this.nextButton().click();
			await this.popup.waitForLoadState();
		}

		// Sometimes the phone may be requested
		if (
			! ( await this.passwordInput().isEditable() ) &&
			this.loginWithPasswordInsteadLink().isVisible()
		) {
			this.loginWithPasswordInsteadLink().click();
			await this.popup.waitForLoadState();
		}

		await this.passwordInput().fill( password );
		await this.loginButton().click();
	};

	clickSubmitButton = async () => {
		await this.popup.waitForLoadState();
		await expect( this.loadSpinnerContainer() ).not.toBeVisible();

		while ( ! this.popup.isClosed() ) {
			const submitButton = this.submitPaymentButton();
			if ( ! await submitButton.isVisible() ) {
				break; // No visible button, exit
			}

			// Race click with popup closure
			try {
				await Promise.race( [
					submitButton.click(),
					this.popup.waitForEvent( 'close', { timeout: 30 * 1000 } ) // Short timeout to prevent hang
				] );
			} catch ( error ) {
				if ( this.popup.isClosed() ) break; // Exit cleanly if popup closed
				throw error; // Rethrow unexpected errors
			}

			// Optional: wait for spinner to disappear
			try {
				await expect( this.loadSpinnerContainer() ).toBeVisible( { timeout: 1000 } );
				await expect( this.loadSpinnerContainer() ).not.toBeVisible( { timeout: 5000 } );
			} catch {
				// Spinner didn't appear, continue
			}
		}
	};

	completePayment = async () => {
		await Promise.all( [
			this.popup.waitForEvent( 'close' ),
			this.clickSubmitButton(),
		] );
	};

	/**
	 * Completes payment with PayPal
	 *
	 * @param payPalAccount
	 */
	completePayPalPayment = async ( payPalAccount: PayPalAccount ) => {
		await this.login( payPalAccount.email, payPalAccount.password );
		await this.completePayment();
	};

	/**
	 * Completes payment with Pay Later
	 *
	 * @param payPalAccount = { "email": "...", "password": "..." }
	 */
	completePayLaterPayment = async ( payPalAccount: PayPalAccount ) => {
		await this.login( payPalAccount.email, payPalAccount.password );
		await expect( this.payLaterSwitcher() ).toHaveAttribute(
			'aria-selected',
			'true'
		);
		await expect( this.submitPaymentButton() ).toBeVisible();
		await expect( this.payLaterRadio() ).toBeVisible();
		await expect( this.payLaterSwitcher() ).toBeEnabled();
		await this.payLaterRadio().check();
		await this.submitPaymentButton().click();
		await this.completePayment();
	};

	/**
	 * Completes payment with Venmo
	 */
	completeVenmoPayment = async () => {
		await this.venmoButton().click();
		await this.completePayment();
	};
}
