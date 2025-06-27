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
		} )
	loginWithYourPasswordLink = () =>
		this.popup.getByRole('link', { name: 'Login with password' });
	tryAnotherWayLink = () =>
		this.popup.getByRole( 'link', { name: 'Try another way', } );
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
			.or( this.popup.locator( '#confirmButtonTop' ) )
			.or( this.popup.locator( '#one-time-cta' ) );
	payLaterSwitcher = () => this.popup.getByTestId( 'paylater-tab' );
	payLaterRadio = () =>
		this.popup.locator( 'label[for^="credit-offer"]' ).first();
	venmoButton = () => this.popup.locator( '.venmo-button-wrapper>button' );
	saveAndContinueButton = () => this.popup.getByTestId( 'consentButton' );
	cancelLink = () => this.popup.locator( '#cancelLink' );
	loadSpinnerContainer = () => this.popup.locator( '#preloaderSpinner' );


	// Actions
	
	/**
	 *  Log in to PayPal
	 * 
	 * @param email 
	 * @param password 
	 */
	login = async ( email, password ) => {
		await this.tryLoginWithPasswordInstead();

		await this.loginInput().fill( email );
		
		await this.tryLoginWithPasswordInstead();

		await this.tryClickNext();

		await this.tryAnotherWay();

		await this.passwordInput().fill( password );
		await this.loginButton().click();
	};

	/**
	 * Tries to click "Login with password instead" button if displayed
	 * Swallows the fail if no button appears
	 */
	tryLoginWithPasswordInstead = async () => {
		try {
			await this.loginWithPasswordInsteadLink().waitFor({ state: 'visible', timeout: 4000 });
			await this.loginWithPasswordInsteadLink().click();
		} catch {}
	};

	/**
	 * Tries to click "Next" button if displayed
	 * Swallows the fail if no button appears
	 */
	tryClickNext = async () => {
		try {
			await this.nextButton().waitFor({ state: 'visible', timeout: 4000 });
			await this.nextButton().click();
		} catch {}
	};

	/**
	 * Tries to click "Try another way" and "Login with your password" buttons if displayed
	 * Swallows the fail if no button appears
	 */
	tryAnotherWay = async () => {
		try {
			await this.tryAnotherWayLink().waitFor({ state: 'visible', timeout: 4000 });
			await this.tryAnotherWayLink().click();
			await this.loginWithYourPasswordLink().click();
		} catch {}
	};

	trySubmitPayment = async () => {
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
				await expect( this.loadSpinnerContainer() ).not.toBeVisible( { timeout: 4000 } );
			} catch {
				// Spinner didn't appear, continue
			}
		}
	};

	completePayment = async () => {
		await Promise.all( [
			this.popup.waitForEvent( 'close' ),
			this.trySubmitPayment(),
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
