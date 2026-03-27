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
			)
			.or( this.page.getByRole( 'button', { name: 'Log In' } ) );
	submitPaymentButton = () =>
		this.page
			.locator( '#payment-submit-btn' )
			.or( this.page.getByTestId( 'submit-button-initial' ) )
			.or( this.page.getByTestId( 'consentButton' ) )
			.or( this.page.getByRole( 'button', { name: 'Continue' } ) )
			.or( this.page.locator( '#confirmButtonTop' ) )
			.or( this.page.locator( '#one-time-cta' ) )
			.or( this.page.getByRole( 'button', { name: /Link and Pay/ } ) )
			// German PayPal consent page (pay/billing flow) uses "Zustimmen und weiter"
			.or( this.page.getByRole( 'button', { name: /Zustimmen/ } ) );
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
		this.payLaterIframe().locator( 'input[type="checkbox"]' ).first();
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

		// Track if Next advanced the page; we need checkout URL for redirect recovery.
		const urlBeforeNext = this.page.url();
		await this.tryClickNext();
		const nextAdvancedPage = this.page.url() !== urlBeforeNext;

		// URL has ctxId/returnUri for redirect recovery.
		const checkoutSigninUrl = this.page.url();

		await this.tryLoginWithPasswordInstead();

		await this.tryAnotherWay();
		await this.tryLoginWithPasswordInstead();

		await this.passwordInput().fill( password );
		await this.loginButton().click();
		// Wait for redirect to consent/vaulting page.
		try {
			await this.passwordInput().waitFor( { state: 'detached', timeout: 30000 } );
		} catch {}
		await this.page.waitForLoadState();

		// If sandbox redirected to myaccount/signin instead of checkout, go back to checkpoint URL.
		const needsRedirect = ( url: string ) =>
			url.includes( 'myaccount' ) || url.includes( 'signin?returnUri' );

		if ( nextAdvancedPage && needsRedirect( this.page.url() ) ) {
			await this.page.goto( checkoutSigninUrl );
			await this.page.waitForLoadState();
		}

		// If sandbox redirected to login again, re-submit credentials.
		try {
			await this.loginInput().waitFor( { state: 'visible', timeout: 3000 } );
			const emailValue = await this.loginInput().inputValue();
			if ( ! emailValue ) {
				await this.loginInput().fill( email );
			}
			await this.passwordInput().fill( password );
			await this.loginButton().click();
			try {
				await this.passwordInput().waitFor( { state: 'detached', timeout: 30000 } );
			} catch {}
			await this.page.waitForLoadState();
			// Apply the same redirect fix for the re-login case.
			if ( nextAdvancedPage && needsRedirect( this.page.url() ) ) {
				await this.page.goto( checkoutSigninUrl );
				await this.page.waitForLoadState();
			}
		} catch {}
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
		// If the CAP iframe is not yet visible, we're on the Pay Later selection
		// screen — click Continue to advance to the confirm details page.
		// If the CAP iframe is already there, we can skip this step.
		const capIframe = this.page.locator( 'iframe[title="CAP"]' );
		const isCapVisible = await capIframe.isVisible().catch( () => false );
		if ( ! isCapVisible ) {
			await this.submitPaymentButton().click();
		}
		// Wait for the CAP iframe to be present and fully loaded.
		await capIframe.waitFor( { state: 'visible', timeout: 30000 } );
		// Click the loan agreement checkbox via JS eval to avoid contentFrame
		// timing race that causes "Target page, context or browser has been closed".
		await this.page.evaluate( () => {
			const iframe = document.querySelector( 'iframe[title="CAP"]' ) as HTMLIFrameElement;
			const checkbox = iframe?.contentDocument?.querySelector( 'input[type="checkbox"]' ) as HTMLInputElement;
			if ( checkbox && ! checkbox.checked ) {
				checkbox.click();
			}
		} );
		// After checking the loan agreement checkbox, PayPal briefly shows a
		// loading overlay inside the CAP iframe that intercepts pointer events.
		await this.payLaterIframe()
			.locator( '[data-testid="loading-overlay"]' )
			.waitFor( { state: 'hidden', timeout: 15000 } )
			.catch( () => {} ); // overlay may not always appear
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
