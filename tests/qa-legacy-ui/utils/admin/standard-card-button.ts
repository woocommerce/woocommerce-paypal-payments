/**
 * Internal dependencies
 */
import { PcpSettingsPage } from './pcp-settings-page';
import urls from '../urls';

export class StandardCardButton extends PcpSettingsPage {
	url = urls.pcp.standardCardButton;

	// Locators
	tabName = () =>
		this.page.locator( '.nav-tab', { hasText: 'Standard Card Button' } );

	// Actions

	/**
	 * Bulk update of Advanced Card Processing tab settings
	 *
	 * @param data
	 */
	setup = async ( data? ) => {
		if ( ! data ) {
			return;
		}

		await this.visit();
		
		// restore checkbox to enable Save Changes button
		const currentValue = await this.enableGatewayCheckbox().isChecked();
		await this.enableGatewayCheckbox().setChecked( ! currentValue );
		await this.enableGatewayCheckbox().setChecked( data.enableGateway ?? currentValue );

		// Add other settings here

		await this.saveChanges();
	};

	// Assertions
}
