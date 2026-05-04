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

	usePasswordInsteadButton = () =>
		this.popup.getByRole( 'button', { name: 'Use Password Instead' } );
	loginWithPasswordInsteadLink = () =>
		this.popup.getByRole( 'link', {
			name: 'Log in with a password instead',
		} );
	loginWithYourPasswordLink = () =>
		this.popup.getByRole( 'link', { name: 'Login with password' } );
	loginWithPasswordInstead = () =>
		this.loginWithPasswordInsteadLink()
			.or( this.usePasswordInsteadButton() )
			.or( this.loginWithYourPasswordLink() );
	tryAnotherWayLink = () =>
		this.popup.getByRole( 'link', { name: 'Try another way' } );
	loginInput = () => this.popup.locator( '[name="login_email"]' );
	passwordInput = () => this.popup.locator( '[name="login_password"]' );
	nextButton = () =>
		this.popup
			.locator( '#btnNext' )
			.or(
				this.popup.locator(
					'button[data-atomic-wait-intent="Submit_Email"]'
				)
			);
	loginButton = () =>
		this.popup
			.locator( '#btnLogin' )
			.or(
				this.popup.locator(
					'button[data-atomic-wait-intent="Submit_Password"]'
				)
			);
	submitPaymentButton = () =>
		this.popup
			.locator( '#payment-submit-btn' )
			.or( this.popup.getByTestId( 'submit-button-initial' ) )
			.or( this.popup.getByTestId( 'consentButton' ) )
			.or( this.popup.getByRole( 'button', { name: 'Continue' } ) )
			.or( this.popup.locator( '#confirmButtonTop' ) )
			.or( this.popup.locator( '#one-time-cta' ) );
	venmoButton = () => this.popup.locator( '.venmo-button-wrapper>button' );
	saveAndContinueButton = () => this.popup.getByTestId( 'consentButton' );
	cancelLink = () => this.popup.locator( '#cancelLink' );
	loadSpinnerContainer = () => this.popup.locator( '#preloaderSpinner' );
	tryAgainButton = () =>
		this.popup
			.getByRole( 'link', { name: 'Erneut versuchen' } )
			.or( this.popup.getByRole( 'link', { name: 'Try again' } ) );
	payLaterIframe = () =>
		this.popup.locator( 'iframe[title="CAP"]' ).contentFrame();
	loanAgreementCheckbox = () =>
		this.payLaterIframe().getByText(
			'You have read and agree to the Loan Agreement'
		);
	agreeAndApplyButton = () => this.payLaterIframe().getByTestId( 'apply' );
	tryAgainLink = () => this.popup.getByRole( 'link', { name: 'Try again' } );

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

		await this.tryClickNext();

		await this.tryLoginWithPasswordInstead();

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
			await this.loginWithPasswordInstead().waitFor( {
				state: 'visible',
				timeout: 4000,
			} );
			await this.loginWithPasswordInstead().click();
			await this.popup.waitForLoadState();
		} catch {}
	};

	/**
	 * Tries to click "Next" button if displayed
	 * Swallows the fail if no button appears
	 */
	tryClickNext = async () => {
		try {
			await this.nextButton().waitFor( {
				state: 'visible',
				timeout: 4000,
			} );
			await this.nextButton().click();
			await this.popup.waitForLoadState();
		} catch {}
	};

	/**
	 * Tries to click "Try another way" and "Login with your password" buttons if displayed
	 * Swallows the fail if no button appears
	 */
	tryAnotherWay = async () => {
		try {
			await this.tryAnotherWayLink().waitFor( {
				state: 'visible',
				timeout: 4000,
			} );
			await this.tryAnotherWayLink().click();
			await this.loginWithYourPasswordLink().click();
		} catch {}
	};

	trySubmitPayment = async () => {
		await this.popup.waitForLoadState();
		await expect( this.loadSpinnerContainer() ).not.toBeVisible();

		while ( ! this.popup.isClosed() ) {
			// Race click with popup closure
			try {
				await Promise.race( [
					this.submitPaymentButton().click(),
					this.tryAgainLink().click(),
					this.popup.waitForEvent( 'close', { timeout: 30_000 } ), // Short timeout to prevent hang
				] );
			} catch ( error ) {
				if ( this.popup.isClosed() ) break; // Exit cleanly if popup closed
				throw error; // Rethrow unexpected errors
			}

			// Optional: wait for spinner to disappear
			try {
				await expect( this.loadSpinnerContainer() ).toBeVisible( {
					timeout: 1000,
				} );
				await expect( this.loadSpinnerContainer() ).not.toBeVisible( {
					timeout: 4000,
				} );
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
	completePayPalPayment = async ( payPalAccount ) => {
		await this.login( payPalAccount.email, payPalAccount.password );
		await this.completePayment();
	};

	/**
	 * Completes payment with Pay Later
	 *
	 * @param payPalAccount = { "email": "...", "password": "..." }
	 */
	completePayLaterPayment = async ( payPalAccount ) => {
		await this.login( payPalAccount.email, payPalAccount.password );
		await this.submitPaymentButton().click();
		await this.loanAgreementCheckbox().click();
		await this.agreeAndApplyButton().click();
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
