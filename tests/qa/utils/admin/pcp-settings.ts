/**
 * Internal dependencies
 */
import { PcpAdminPage } from './pcp-admin-page';
import urls from '../urls';

export class PcpSettings extends PcpAdminPage {
	url = urls.admin.pcp.settings;

	// Locators
	disconnectButton = () =>
		this.page.getByRole( 'button', { name: 'Disconnect' } );

	modalDisconnectButton = () =>
		this.modalContainer().getByRole( 'button', { name: 'Disconnect' } );
	modalCancelButton = () =>
		this.modalContainer().getByRole( 'button', { name: 'Cancel' } );
	modalStartOverToggle = () => this.page.getByLabel( 'Start over' );

	merchantIdText = () =>
		this.settingBlock( 'Merchant ID' ).locator( '.ppcp--static-value' );
	merchantEmailAddressText = () =>
		this.settingBlock( 'Email address' ).locator( '.ppcp--static-value' );
	merchantClientIdText = () =>
		this.settingBlock( 'Client ID' ).locator( '.ppcp--static-value' );

	// Actions

	// Assertions
}
