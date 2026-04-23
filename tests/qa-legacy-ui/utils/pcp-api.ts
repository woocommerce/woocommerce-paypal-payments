/**
 * External dependencies
 */
import {
	expect,
	RequestUtils,
	WooCommerceApi as WooCommerceApiBase,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PcpMerchant } from '../resources';
import urls from './urls';
import { generateRandomString } from './helpers';

/**
 * Class for REST API interactions with PCP Settings.
 */
export class PcpApi extends WooCommerceApiBase {
	requestUtils: RequestUtils;

	constructor( { request, requestUtils } ) {
		super( { request } );
		this.requestUtils = requestUtils;
	}
	
	/**
	 * Onboard with Pay Upon Invoice (PUI)
	 * Only for German merchant
	 *
	 */
	onboardWithPui = async () => {
		const nonce = await this.requestUtils.getRegexMatchValueOnPage(
			urls.pcp.connection,
			/"update_signup_links_nonce":"([^"]+)"/
		);

		const response = await this.requestUtils.request.post(
			'/?wc-ajax=ppc-update-signup-links',
			{
				data: {
					nonce,
					settings: { 'ppcp-onboarding-pui': true },
				},
			}
		);
		const result = response.ok();
		await expect( result ).toBeTruthy();
		return result;
	};
	
	/**
	 * Connects merchant via form post request
	 *
	 * @param merchant
	 * @param options
	 */
	connectMerchant = async (
		merchant: PcpMerchant,
		options = {
			enablePayUponInvoice: false,
		}
	) => {
		const ppcpNonce = await this.requestUtils.getRegexMatchValueOnPage(
			urls.pcp.connection,
			/<input type="hidden" name="ppcp-nonce" value="([^"]+)">/
		);

		const wpnonce = await this.requestUtils.getPageNonce(
			urls.pcp.connection
		);

		const formData = {
			_wpnonce: wpnonce,
			'ppcp-nonce': ppcpNonce,
			'ppcp[sandbox_on]': '1',
			'ppcp[merchant_email_production]': '',
			'ppcp[merchant_id_production]': '',
			'ppcp[client_id_production]': '',
			'ppcp[client_secret_production]': '',
			'ppcp[merchant_email_sandbox]': merchant.email,
			'ppcp[merchant_id_sandbox]': merchant.account_id,
			'ppcp[client_id_sandbox]': merchant.client_id,
			'ppcp[client_secret_sandbox]': merchant.client_secret,
			'ppcp[soft_descriptor]': '',
			'ppcp[prefix]': `${ generateRandomString( 10 ) }-`,
			'ppcp[stay_updated]': '1',
			'ppcp[subtotal_mismatch_behavior]': 'extra_line',
			'ppcp[subtotal_mismatch_line_name]': '',
			save: 'Save changes',
		};

		if ( options.enablePayUponInvoice === true ) {
			formData.ppcp_onboarding_dcc = 'basic';
			await this.onboardWithPui();
		}

		const response = await this.requestUtils.submitPageForm(
			urls.pcp.connection,
			formData
		);
		const result = response.ok();
		await expect( result ).toBeTruthy();
		return result;
	};

	/**
	 * Disconnects merchant via form post request
	 *
	 */
	disconnectMerchant = async () => {
		const ppcpNonce = await this.requestUtils.getRegexMatchValueOnPage(
			urls.pcp.connection,
			/<input type="hidden" name="ppcp-nonce" value="([^"]+)">/
		);
		const wpnonce = await this.requestUtils.getPageNonce(
			urls.pcp.connection
		);
		const formData = {
			_wpnonce: wpnonce,
			'ppcp-nonce': ppcpNonce,
			'ppcp[merchant_email_production]': '',
			'ppcp[merchant_id_production]': '',
			'ppcp[client_id_production]': '',
			'ppcp[client_secret_production]': '',
			'ppcp[merchant_email_sandbox]': '',
			'ppcp[merchant_id_sandbox]': '',
			'ppcp[client_id_sandbox]': '',
			'ppcp[client_secret_sandbox]': '',
			'ppcp[soft_descriptor]': '',
			'ppcp[prefix]': '',
			'ppcp[stay_updated]': '1',
			'ppcp[subtotal_mismatch_behavior]': 'extra_line',
			'ppcp[subtotal_mismatch_line_name]': '',
			save: 'Save changes',
		};
		const response = await this.requestUtils.submitPageForm(
			urls.pcp.connection,
			formData
		);
		const result = response.ok();
		await expect( result ).toBeTruthy();
		return result;
	};

	/**
	 * Clear PCP DB via request
	 *
	 */
	clearPcpDb = async () => {
		const nonce = await this.requestUtils.getRegexMatchValueOnPage(
			urls.pcp.connection,
			/"clearDb":\{[^}]*"nonce":"([^"]+)"/
		);

		const response = await this.requestUtils.request.post(
			'/?wc-ajax=ppcp-clear-db',
			{ data: { nonce } }
		);
		const result = response.ok();
		await expect( result ).toBeTruthy();
		return result;
	};

	/**
	 * Sets OXXO
	 *
	 * @param merchant
	 * @param options
	 */
	setOxxo = async ( data ) => {
		const { enableGateway, title, description } = data;

		const wpnonce = await this.requestUtils.getPageNonce(
			urls.pcp.oxxo
		);

		const formData = {
			_wpnonce: wpnonce,
			'woocommerce_ppcp-oxxo-gateway_enabled': ( enableGateway ?? true ) ? '1' : '0',
			'woocommerce_ppcp-oxxo-gateway_title': title ?? 'OXXO',
			'woocommerce_ppcp-oxxo-gateway_description': description ?? 'OXXO allows you to pay bills and online purchases in-store with cash.',
			save: 'Save changes',
		};

		const response = await this.requestUtils.submitPageForm(
			urls.pcp.oxxo,
			formData
		);
		const result = response.ok();
		await expect( result ).toBeTruthy();
		return result;
	};
}
