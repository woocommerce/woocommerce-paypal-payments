/**
 * External dependencies
 */
import { expect, Page } from '@playwright/test';

export class PayPalConnectionPopup {
	popup: Page;

	constructor( { popup } ) {
		this.popup = popup;
	}

	// Locators
	acceptCookiesButton = () => this.popup.locator( '#acceptAllButton' );
	emailInput = () => this.popup.locator( '#email' );
	// countryCombobox = () => this.popup.locator(''); // TODO: provide locator
	continueButton = () => this.popup.locator('#continueButton');
	passwordInput = () => this.popup.locator('#password');
	loginButton = () => this.popup.locator('#btnLogin');
	agreeAndConnectButton = () =>
		this.popup.locator('#agreeAndConnectButton', {
			hasText: 'Agree and Connect'
		} );
	goBackToFacilitatorTestStoreButton = () =>
		this.popup.locator('.Button--primary', {
			hasText: "Go back to test facilitator's Test Store"
		} );

	// Actions

	login = async ( email: string, password: string, country?: string ) => {
		await expect( this.popup ).toHaveTitle(
			'Connect a PayPal account to start accepting payments on test facilitator\'s Test Store'
		);
		await this.emailInput().fill( email );
		if( country ) { /* TODO: select country */ }
		await this.continueButton().click();
		await this.passwordInput().fill( password );
		await this.loginButton().click();
		await this.continueButton().click();
		await this.agreeAndConnectButton().click();
		await this.goBackToFacilitatorTestStoreButton().click();
		await this.popup.waitForEvent( 'close' );
	};
}
