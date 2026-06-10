/**
 * External dependencies
 */
import { expect, WpPage } from '@inpsyde/playwright-utils/build';
import { Locator, LocatorScreenshotOptions } from '@playwright/test';

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

	// PCP Settings tabs when merchant is connected
	overviewTab = () =>
		this.page.getByRole( 'tab', { name: 'Overview', exact: true } );
	paymentMethodsTab = () =>
		this.page.getByRole( 'tab', { name: 'Payment Methods', exact: true } );
	settingsTab = () =>
		this.page.getByRole( 'tab', { name: 'Settings', exact: true } );
	stylingTab = () =>
		this.page.getByRole( 'tab', { name: 'Styling', exact: true } );
	payLaterMessagingTab = () =>
		this.page.getByRole( 'tab', {
			name: 'Pay Later Messaging',
			exact: true,
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
	 * @param locator
	 * @param snapshotName
	 * @param options
	 */
	snapshotLocator = async (
		locator: Locator,
		snapshotName: string,
		options?: LocatorScreenshotOptions & {
			maxDiffPixelRatio?: number;
			maxDiffPixels?: number;
			threshold?: number;
		}
	) => {
		options = {
			timeout: 500,
			animations: 'disabled',
			style: '#adminmenuback, #adminmenuwrap, #wpadminbar, .ppcp-r-navigation-container { display: none !important; }',
			threshold: 0.8,
			...options,
		};
		// Assert message is displayed
		await expect.soft( locator ).toBeVisible();
		// Wait for potential animation
		await this.page.waitForTimeout( options.timeout );
		// Take actual screenshot of configurator and compare to expected
		expect
			.soft( await locator.screenshot( options ) )
			.toMatchSnapshot( `${ snapshotName }.png`, options );
	};

	/**
	 * Compares actual content container screenshot to expected.
	 *
	 * @param snapshotName
	 * @param timeout
	 */
	snapshotContent = async ( snapshotName: string, timeout = 500 ) =>
		this.snapshotLocator( this.contentContainer(), snapshotName, {
			timeout,
		} );
}
