/**
 * Internal dependencies
 */
import { PcpSettingsPage } from './pcp-settings-page';
import urls from '../urls';

export class PayLater extends PcpSettingsPage {
	url = urls.pcp.payLater;

	// Locators
	enableGatewayCheckbox = () =>
		this.page.locator( '#ppcp-pay_later_button_enabled' );
	enablePayLaterMessagingCheckbox = () =>
		this.page.locator( '#ppcp-pay_later_messaging_enabled' );
	cartPageInPayLaterButtonLocations = () =>
		this.page
			.locator(
				'#field-pay_later_button_locations .select2-selection__choice[title="Cart"]'
			)
			.locator( '.select2-selection__choice__remove' );

	// Actions

	/**
	 * Bulk update of Pay Later tab settings
	 *
	 * @param data
	 */
	setup = async ( data? ) => {
		if ( ! data ) {
			return;
		}

		await this.visit();

		if ( data.enableGateway !== undefined ) {
			await this.enableGatewayCheckbox().setChecked( data.enableGateway );
		}

		// Add other settings here

		await this.saveChanges();
	};

	// Assertions
}
