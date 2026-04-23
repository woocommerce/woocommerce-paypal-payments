/**
 * Internal dependencies
 */
import { PcpSettingsPage } from './pcp-settings-page';
import urls from '../urls';

export class OXXO extends PcpSettingsPage {
	url = urls.pcp.oxxo;

	// Locators

	// Actions

	/**
	 * Bulk update of OXXO tab settings
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
