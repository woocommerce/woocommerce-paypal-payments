/**
 * External dependencies
 */
import { expect, Page } from '@playwright/test';

export class ApmHostedCheckout {
	page: Page;
	url = 'https://www.sandbox.paypal.com/apmsim/';

	constructor( page: Page ) {
		this.page = page;
	}

	// Locators
	testSuccessfulPaymentButton = () =>
		this.page.getByRole( 'button', { name: 'Test Successful Payment' } );

	// Actions

	// Assertions

	assertUrl = async () => {
		await expect(
			this.page,
			'Assert APM hosted checkout page URL'
		).toHaveURL( new RegExp( `^${ this.url }` ) );
	};
}
