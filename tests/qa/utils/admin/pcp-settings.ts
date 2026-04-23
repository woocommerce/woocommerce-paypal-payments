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
	connectionDetailsContainer = () =>
		this.page.locator( '.ppcp-connection-details' );
	merchantIdValue = () =>
		this.settingBlock( 'Merchant ID' ).locator( '.ppcp--static-value' );
	merchantEmailAddressValue = () =>
		this.settingBlock( 'Email address' ).locator( '.ppcp--static-value' );
	merchantClientIdValue = () =>
		this.settingBlock( 'Client ID' ).locator( '.ppcp--static-value' );

	// Actions

	// Assertions
}
