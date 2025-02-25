/**
 * External dependencies
 */
import { WpPage } from '@inpsyde/playwright-utils/build';

export class PcpAdminPage extends WpPage {
	// Locators
	navigationPanel = () => this.page.locator( '.ppcp-r-navigation' );
	backButton = () => this.navigationPanel().locator( 'button.is-title' );
	pageTitle = ( title: string ) => this.backButton().getByText( title );
	saveButton = () =>
		this.navigationPanel().getByRole( 'button', { name: 'Save' } );
	modalContainer = () =>
		this.page.locator( '.components-modal__content[role="document"]' );

	// Actions

	/**
	 * Clicks Save button and Waits for requests
	 */
	saveChanges = async () => {
		await this.saveButton().click();
		await this.page.waitForLoadState( 'networkidle' );
	};
}
