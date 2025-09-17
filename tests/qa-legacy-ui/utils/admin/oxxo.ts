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

		if ( data.enableGateway !== undefined ) {
			await this.enableGatewayCheckbox().setChecked( data.enableGateway );
		}

		// Add other settings here

		await this.saveChanges();
	};

	// Assertions
}
