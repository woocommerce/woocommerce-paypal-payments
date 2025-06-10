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
			.or( this.popup.getByTestId( 'submit-button-initial' ) );
	payLaterSwitcher = () => this.popup.getByTestId( 'paylater-tab' );
	payLaterRadio = () =>
		this.popup.locator( 'label[for^="credit-offer"]' ).first();
	venmoButton = () => this.popup.locator( '.venmo-button-wrapper>button' );
	saveAndContinueButton = () => this.popup.getByTestId( 'consentButton' );
	cancelLink = () => this.popup.locator( '#cancelLink' );

	// Actions

	login = async ( email, password ) => {
		await expect( this.popup ).toHaveTitle(
			'Log in to your PayPal account'
		);

		// Sometimes the phone may be requested
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

		// Sometimes the phone may be requested
		if (
			! ( await this.passwordInput().isEditable() ) &&
			this.loginWithPasswordInsteadLink().isVisible()
		) {
			this.loginWithPasswordInsteadLink().click();
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

	/**
	 * Completes payment with PayPal
	 *
	 * @param payPalAccount
	 */
	completePayPalPayment = async ( payPalAccount: PayPalAccount ) => {
		await this.login( payPalAccount.email, payPalAccount.password );
		await expect( this.popup ).toHaveTitle( 'PayPal Checkout' );
		await this.completePayment();
	};

	/**
	 * Completes payment with PayPal
	 */
	completePayPalVaultedPayment = async () => {
		await expect( this.popup ).toHaveTitle( 'PayPal Checkout' );
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

	/**
	 * Adds PayPal as customer's saved payment method (Vaulting)
	 *
	 * @param payPalAccount = { "email": "...", "password": "..." }
	 */
	savePayPalPaymentMethod = async ( payPalAccount: PayPalAccount ) => {
		await expect( this.popup ).toHaveTitle(
			'Log in to your PayPal account'
		);
		await this.login( payPalAccount.email, payPalAccount.password );
		await expect( this.popup ).toHaveTitle(
			'PayPal Checkout - Review your payment'
		);
		await this.savePaymentMethodAndContinue();
	};
}
