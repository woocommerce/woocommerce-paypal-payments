/**
 * Internal dependencies
 */
import { PcpSettingsPage } from './pcp-settings-page';
import urls from '../urls';

export class PayUponInvoice extends PcpSettingsPage {
	url = urls.pcp.payUponInvoice;

	// Locators
	logoUrlInput = () =>
		this.page.locator(
			'#woocommerce_ppcp-pay-upon-invoice-gateway_logo_url'
		);
	customerServiceInstructionsInput = () =>
		this.page.locator(
			'#woocommerce_ppcp-pay-upon-invoice-gateway_customer_service_instructions'
		);
	brandNameInput = () =>
		this.page.locator(
			'#woocommerce_ppcp-pay-upon-invoice-gateway_brand_name'
		);
	titleInput = () =>
		this.page.locator( '#woocommerce_ppcp-pay-upon-invoice-gateway_title' );
	descriptionInput = () =>
		this.page.locator(
			'#woocommerce_ppcp-pay-upon-invoice-gateway_description'
		);

	// Actions

	/**
	 * Bulk update of Pay upon Invoice tab settings
	 *
	 * @param data
	 */
	setup = async ( data? ) => {
		data = data || {};
		await this.visit();

		if ( data.enableGateway !== undefined ) {
			await this.logoUrlInput().fill(
				'https://s1.significados.com/foto/piramide-73.jpg'
			); // mandatory to activate PUI
			await this.customerServiceInstructionsInput().fill(
				'Test instructions'
			); // mandatory to activate PUI
			await this.enableGatewayCheckbox().setChecked( data.enableGateway );
		}

		// Add other settings here

		await this.saveChanges();
	};

	// Assertions
}
