/**
 * Internal dependencies
 */
import { PcpSettingsPage } from './pcp-settings-page';
import urls from '../urls';
import { expect } from '../../utils';

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

		// restore checkbox to enable Save Changes button
		const enableGatewayCheckbox = this.enableGatewayCheckbox();
		await expect(
			enableGatewayCheckbox,
			'Assert enable Gateway checkbox is visible.'
		).toBeVisible();
		const currentValue = await enableGatewayCheckbox.isChecked();
		await enableGatewayCheckbox.setChecked( ! currentValue );
		await enableGatewayCheckbox.setChecked( data.enableGateway ?? currentValue );

		// Add other settings here
		if ( data.vaulting !== undefined ) {
			const vaultingCheckbox = this.vaultingCheckbox();
			await expect(
				vaultingCheckbox,
				'Assert vaulting checkbox is visible.'
			).toBeVisible();
			await vaultingCheckbox.check();
		}

		if ( data.threeDSecure ) {
			const contingencyFor3DSecureDropdown = this.contingencyFor3DSecureDropdown();
			await expect(
				contingencyFor3DSecureDropdown,
				'Assert 3D Secure contingency dropdown is visible.'
			).toBeVisible();
			await contingencyFor3DSecureDropdown.click();
			const dropdownOption = this.dropdownOption( data.threeDSecure );
			await expect(
				dropdownOption,
				`Assert 3D Secure contingency dropdown option "${ data.threeDSecure }" is visible.`
			).toBeVisible();
			await dropdownOption.click();
		}

		await this.saveChanges();
	};

	// Assertions
}
