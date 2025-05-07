/**
 * Internal dependencies
 */
import { expect } from '../test';
import { PcpAdminPage } from './pcp-admin-page';
import urls from '../urls';
import { Pcp } from '../../resources';

export class PcpStyling extends PcpAdminPage {
	url = urls.admin.pcp.styling;

	// Locators
	configContainer = () => this.page.locator( '.ppcp-r-styling' );
	locationSelectbox = () =>
		this.page.locator( '#inspector-select-control-0' );
	enablePaymentMethodsOnLocationCheckbox = () =>
		this.page.locator( '#inspector-checkbox-control-0' );
	paymentMethodsCheckbox = ( label: Pcp.Admin.Styling.PaymentMethods ) =>
		this.page.getByLabel( label );
	buttonLayoutRadio = ( label: Pcp.Admin.Styling.ButtonLayout ) =>
		this.page.getByLabel( label );
	showTaglineBelowButtonsCheckbox = () =>
		this.page.getByLabel( 'Show tagline below buttons' );
	buttonShapeRadio = ( label: Pcp.Admin.Styling.ButtonShape ) =>
		this.page.getByLabel( label );
	buttonLabelSelectbox = () =>
		this.page.locator( '#inspector-select-control-30' );
	buttonColorSelectbox = () =>
		this.page.locator( '#inspector-select-control-31' );

	payPalButtonsPreviewIframe = () =>
		this.page.frameLocator( 'iframe[name^="__zoid__paypal_buttons__"]' );
	payPalButtonsPreviewContainer = () =>
		this.payPalButtonsPreviewIframe().locator( '#buttons-container' );

	// Actions

	// Assertions

	/**
	 * Compares actual configurator screenshot to expected.
	 *
	 * @param snapshotName
	 */
	snapshotStylingConfigurator = async ( snapshotName: string ) => {
		// Assert message is displayed
		await expect( this.payPalButtonsPreviewContainer() ).toBeVisible();
		// Screenshot configurator
		this.snapshotLocator(
			this.configContainer(),
			snapshotName,
		);
	};
}
