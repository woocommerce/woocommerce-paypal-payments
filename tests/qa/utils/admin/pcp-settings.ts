/**
 * Internal dependencies
 */
import { PcpAdminPage } from './pcp-admin-page';
import urls from '../urls';

export class PcpSettings extends PcpAdminPage {
	url = urls.admin.pcp.settings;

	// Locators
	connectionDetailsContainer = () =>
		this.page.locator( '.ppcp-connection-details' );

	// Actions

	// Assertions
}
