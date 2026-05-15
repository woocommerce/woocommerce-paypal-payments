/**
 * External dependencies
 */
import { Page } from '@playwright/test';

export class OxxoVoucherPopup {
	page: Page;

	constructor( page: Page ) {
		this.page = page;
	}

	// Locators

	testSuccessfulPaymentButton = () =>
		this.page.getByRole( 'button', { name: 'Test Successful Payment' } );

	// Actions

	simulate = async () => {
		await this.page.waitForLoadState();

		await this.page.evaluate( () => {
			window.opener = null;
		} );

		await this.testSuccessfulPaymentButton().click();

		await this.page
			.waitForEvent( 'close', { timeout: 15_000 } )
			.catch( async () => {
				console.warn(
					'OXXO voucher popup did not auto-close — payment simulation may not have fired the webhook'
				);
				await this.page.close();
			} );
	};
}
