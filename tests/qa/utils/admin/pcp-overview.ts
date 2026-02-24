/**
 * Internal dependencies
 */
import { PcpAdminPage } from './pcp-admin-page';
import urls from '../urls';

export class PcpOverview extends PcpAdminPage {
	url = urls.admin.pcp.overview;

	// Locators
	overviewContainer = () => this.page.locator( '.ppcp-r-tab-overview' );

	// Actions

	// Assertions
}
