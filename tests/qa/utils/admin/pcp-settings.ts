/**
 * Internal dependencies
 */
import { PcpAdminPage } from './pcp-admin-page';
import urls from '../urls';
import { Pcp } from 'resources';

export class PcpSettings extends PcpAdminPage {
	url = urls.admin.pcp.settings;

	// Locators
	disconnectButton = () =>
		this.page.getByRole( 'button', { name: 'Disconnect' } );
	
	modalDisconnectButton = () =>
		this.modalContainer().getByRole( 'button', { name: 'Disconnect' } );
	modalCancelButton = () =>
		this.modalContainer().getByRole( 'button', { name: 'Cancel' } );
	modalStartOverToggle = () =>
		this.page.getByLabel( 'Start over' );

	merchantIdText = () =>
		this.settingBlock( 'Merchant ID' ).locator( '.ppcp--static-value' );
	merchantEmailAddressText = () =>
		this.settingBlock( 'Email address' ).locator( '.ppcp--static-value' );
	merchantClientIdText = () =>
		this.settingBlock( 'Client ID' ).locator( '.ppcp--static-value' );


	// Actions
	isExpectedMerchantConnected = async ( merchant: Pcp.Merchant ) => {
		const isExpectedMerchantId =
			await this.merchantIdText().textContent() === merchant.account_id;
		const isExpectedMerchantEmailAddress =
			await this.merchantEmailAddressText().textContent() === merchant.email;
		const isExpectedMerchantClientId =
			await this.merchantClientIdText().textContent() === merchant.client_id;

		return isExpectedMerchantId
			&& isExpectedMerchantEmailAddress
			&& isExpectedMerchantClientId;
	}

	// Assertions
}
