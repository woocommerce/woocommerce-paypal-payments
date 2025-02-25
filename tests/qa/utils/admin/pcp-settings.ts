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
	startOverToggle = () =>
		this.page.getByLabel( 'Start over' );


	// Actions

	// Assertions
}
