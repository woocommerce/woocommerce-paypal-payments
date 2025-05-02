/**
 * External dependencies
 */
import { expect, WpPage } from '@inpsyde/playwright-utils/build';

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
	completedMessage = () =>
		this.page.locator( '.ppcp-r-navbar-notice' ).getByText( 'Completed' );
	contentContainer = () =>
		this.page.locator(
			'.ppcp-r-container.ppcp-r-container--card.ppcp-r-container--settings'
		);
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
		await this.completedMessage().waitFor( { state: 'visible' } );
	};

	/**
	 * Waits until 'networkidle' and loading spinner is detached.
	 * May be helpful after .visit() on PCP settings pages.
	 */
	waitForLoadingMaskRemoved = async () => {
		await this.page.waitForLoadState();
		await this.loadingMask().waitFor( { state: 'detached' } );
	};

	// Assertions

	/**
	 * Compares actual content container screenshot to expected.
	 *
	 * @param snapshotName
	 */
	snapshotContent = async ( snapshotName: string ) => {
		// Assert message is displayed
		await expect( this.contentContainer() ).toBeVisible();
		// Wait for potential animation
		await this.page.waitForTimeout( 500 );
		// Take actual screenshot of configurator and compare to expected
		expect
			.soft(
				await this.contentContainer().screenshot( {
					animations: 'disabled',
					style: '#wpadminbar, .ppcp-r-navigation-container { display: none; }',
				} )
			)
			.toMatchSnapshot( `${ snapshotName }.png`, { threshold: 0.8 } );
	};
}
