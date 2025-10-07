/**
 * Internal dependencies
 */
import { PcpSettingsPage } from './pcp-settings-page';
import urls from '../urls';

export class AdvancedCardProcessing extends PcpSettingsPage {
	url = urls.pcp.advancedCardProcessing;

	// Locators
	tabName = () =>
		this.page.locator( '.nav-tab', {
			hasText: 'Advanced Card Processing',
		} );
	// enableGatewayCheckbox = () => this.page.locator('#ppcp-dcc_enabled');
	titleInput = () => this.page.locator( '#ppcp-dcc_gateway_title' );
	vaultingCheckbox = () => this.page.locator( '#ppcp-vault_enabled_dcc' );
	contingencyFor3DSecureDropdown = () =>
		this.page.locator( '#select2-ppcp-3d_secure_contingency-container' );

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

		if ( data.enableGateway !== undefined ) {
			await this.enableGatewayCheckbox().setChecked( data.enableGateway );
		}

		// Add other settings here
		if ( data.vaulting !== undefined ) {
			await this.vaultingCheckbox().check();
		}

		if ( data.threeDSecure ) {
			await this.contingencyFor3DSecureDropdown().click();
			await this.dropdownOption( data.threeDSecure ).click();
		}

		await this.saveChanges();
	};

	// Assertions
}
