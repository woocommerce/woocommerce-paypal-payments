/**
 * External dependencies
 */
import { expect, Page } from '@playwright/test';
/**
 * Internal dependencies
 */
import { PayPalAccount } from '../../resources';

export class PayPalPopup {
	page: Page;

	constructor( page ) {
		this.page = page;
	}

	// Locators

	usePasswordInsteadButton = () =>
		this.page.getByRole( 'button', { name: 'Use Password Instead' } );
	loginWithPasswordInsteadLink = () =>
		this.page.getByRole( 'link', {
			name: 'Log in with a password instead',
		} );
	loginWithYourPasswordLink = () =>
		this.page.getByRole( 'link', { name: 'Login with password' } );
	loginWithPasswordInstead = () =>
		this.loginWithPasswordInsteadLink()
			.or( this.usePasswordInsteadButton() )
			.or( this.loginWithYourPasswordLink() );
	tryAnotherWayLink = () =>
		this.page
			.getByRole( 'link', { name: 'Try another way' } )
			.or( this.page
				.getByRole( 'button', { name: 'Try another way' } )
			);
	loginInput = () => this.page.locator( '[name="login_email"]' );
	passwordInput = () => this.page.locator( '[name="login_password"]' );
	nextButton = () =>
		this.page
			.locator( '#btnNext' )
			.or(
				this.page.locator(
					'button[data-atomic-wait-intent="Submit_Email"]'
				)
			);
	loginButton = () =>
		this.page
			.locator( '#btnLogin' )
			.or(
				this.page.locator(
					'button[data-atomic-wait-intent="Submit_Password"]'
				)
			);
	submitPaymentButton = () =>
		this.page
			.locator( '#payment-submit-btn' )
			.or( this.page.getByTestId( 'submit-button-initial' ) )
			.or( this.page.getByTestId( 'consentButton' ) )
			.or( this.page.getByRole( 'button', { name: 'Continue' } ) )
			.or( this.page.locator( '#confirmButtonTop' ) )
			.or( this.page.locator( '#one-time-cta' ) );
	payLaterSwitcher = () => this.page.getByTestId( 'paylater-tab' );
	payLaterRadio = () =>
		this.page.locator( 'label[for^="credit-offer"]' ).first();
	venmoButton = () => this.page.locator( '.venmo-button-wrapper>button' );
	saveAndContinueButton = () => this.page.getByTestId( 'consentButton' );
	cancelLink = () => this.page.locator( '#cancelLink' );
	loadSpinnerContainer = () => this.page.locator( '#preloaderSpinner' );
	tryAgainLink = () => this.page.getByRole( 'link', { name: 'Try again' } );
	payLaterIframe = () =>
		this.page.locator( 'iframe[title="CAP"]' ).contentFrame();
	loanAgreementCheckbox = () =>
		this.payLaterIframe().getByText(
			'You have read and agree to the Loan Agreement'
		);
	agreeAndApplyButton = () => this.payLaterIframe().getByTestId( 'apply' );
	changeUserButton = () => this.page.locator( 'button[aria-label="Change user"]');

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
		await this.tryLoginWithPasswordInstead();

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
			await this.page.waitForLoadState();
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
			await this.page.waitForLoadState();
		} catch {}
	};

	/**
	 * Tries to click "Cahnge" link/button if displayed
	 * Swallows the fail if no button appears
	 */
	tryChangeUser = async () => {
		try {
			await this.changeUserButton().waitFor( {
				state: 'visible',
				timeout: 4000,
			} );
			await this.changeUserButton().click();
			await this.page.waitForLoadState();
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
			await this.page.waitForLoadState();
		} catch {}
	};

	trySubmitPayment = async () => {
		await this.page.waitForLoadState();
		await expect( this.loadSpinnerContainer(), 'Assert load spinner is not visible' ).not.toBeVisible();

		while ( ! this.page.isClosed() ) {
			// Race click with popup closure
			try {
				await Promise.race( [
					this.submitPaymentButton().click(),
					this.tryAgainLink().click(),
					this.page.waitForEvent( 'close', { timeout: 30 * 1000 } ), // Short timeout to prevent hang
				] );
			} catch ( error ) {
				if ( this.page.isClosed() ) break; // Exit cleanly if popup closed
				throw error; // Rethrow unexpected errors
			}

			// Optional: wait for spinner to disappear
			try {
				await expect(
					this.loadSpinnerContainer(),
					'Assert load spinner is visible'
			 	).toBeVisible( {
					timeout: 1000,
				} );
				await expect(
					this.loadSpinnerContainer(),
					'Assert load spinner is not visible'
				).not.toBeVisible( {
					timeout: 4000,
				} );
			} catch {
				// Spinner didn't appear, continue
			}
		}
	};

	completePayment = async () => {
		await Promise.all( [
			this.page.waitForEvent( 'close' ),
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
