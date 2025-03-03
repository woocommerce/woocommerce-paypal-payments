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
	loadingMask = () => this.page.locator( '.ppcp--spinner-message' );

	settingLabel = ( labelText: string ) =>
		this.page.locator( '.ppcp--title' ).filter( { hasText: labelText } );
	settingBlock = ( labelText: string ) =>
		this.page.locator( 'ppcp-r-settings-block' ).filter( {
			has: this.settingLabel( labelText ),
		} );

	// Actions

	/**
	 * Clicks Save button and Waits for requests
	 */
	saveChanges = async () => {
		await this.saveButton().click();
		await this.page.waitForLoadState( 'networkidle' );
	};

	/**
	 * Waits until loading spinner is detached.
	 * May be needed after .visit() on PCP settings pages.
	 */
	waitForLoadingMaskRemoved = async () => {
		await this.page.waitForLoadState( 'networkidle' );
		await this.loadingMask().waitFor( { state: 'detached' } );
	};
}
